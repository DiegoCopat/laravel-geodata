<?php

namespace DiegoCopat\LaravelGeodata\Services;

use DiegoCopat\LaravelGeodata\Models\City;
use DiegoCopat\LaravelGeodata\Models\Country;
use Illuminate\Support\Carbon;

class FiscalCodeService
{
    /**
     * Month letter codes for fiscal code calculation.
     */
    private const MONTH_CODES = [
        1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'H',
        7 => 'L', 8 => 'M', 9 => 'P', 10 => 'R', 11 => 'S', 12 => 'T',
    ];

    /**
     * Even position values for check character calculation.
     */
    private const EVEN_VALUES = [
        '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4,
        '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
        'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4,
        'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9,
        'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14,
        'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19,
        'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25,
    ];

    /**
     * Odd position values for check character calculation.
     */
    private const ODD_VALUES = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9,
        '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9,
        'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11,
        'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
    ];

    /**
     * Omocodia substitution: digit → letter.
     */
    private const OMOCODIA_MAP = [
        '0' => 'L', '1' => 'M', '2' => 'N', '3' => 'P', '4' => 'Q',
        '5' => 'R', '6' => 'S', '7' => 'T', '8' => 'U', '9' => 'V',
    ];

    /**
     * Reverse omocodia: letter → digit.
     */
    private const OMOCODIA_REVERSE = [
        'L' => '0', 'M' => '1', 'N' => '2', 'P' => '3', 'Q' => '4',
        'R' => '5', 'S' => '6', 'T' => '7', 'U' => '8', 'V' => '9',
    ];

    /**
     * Positions (0-indexed) where omocodia substitution can occur.
     */
    private const OMOCODIA_POSITIONS = [6, 7, 9, 10, 12, 13, 14];

    /**
     * Generate a fiscal code from personal data.
     *
     * @param array{
     *     firstname: string,
     *     lastname: string,
     *     gender: string,
     *     birth_date: string,
     *     birth_city?: string,
     *     birth_province?: string,
     *     birth_country?: string,
     *     belfiore_code?: string,
     * } $data
     */
    public function generate(array $data): string
    {
        $surname = $this->encodeSurname($data['lastname']);
        $firstname = $this->encodeFirstname($data['firstname']);

        $birthDate = Carbon::parse($data['birth_date']);
        $year = str_pad($birthDate->year % 100, 2, '0', STR_PAD_LEFT);
        $month = self::MONTH_CODES[$birthDate->month];
        $day = $birthDate->day;

        if (strtoupper($data['gender']) === 'F') {
            $day += 40;
        }
        $day = str_pad((string) $day, 2, '0', STR_PAD_LEFT);

        $belfiore = $this->resolveBelfioreCode($data);

        $partial = $surname . $firstname . $year . $month . $day . $belfiore;
        $checkChar = $this->calculateCheckCharacter($partial);

        return strtoupper($partial . $checkChar);
    }

    /**
     * Validate a fiscal code format and check character.
     */
    public function validate(string $fiscalCode): bool
    {
        $fiscalCode = strtoupper(trim($fiscalCode));

        if (strlen($fiscalCode) !== 16) {
            return false;
        }

        if (! preg_match('/^[A-Z]{6}[A-Z0-9]{2}[ABCDEHLMPRST][A-Z0-9]{2}[A-Z][A-Z0-9]{3}[A-Z]$/i', $fiscalCode)) {
            // Try normalizing omocodia before rejecting
            $normalized = $this->normalizeOmocodia($fiscalCode);
            if (! preg_match('/^[A-Z]{6}\d{2}[ABCDEHLMPRST]\d{2}[A-Z]\d{3}[A-Z]$/i', $normalized)) {
                return false;
            }
        }

        $expected = $this->calculateCheckCharacter(substr($fiscalCode, 0, 15));

        return $fiscalCode[15] === $expected;
    }

    /**
     * Parse a fiscal code and extract personal data.
     *
     * @return array{
     *     surname_code: string,
     *     firstname_code: string,
     *     birth_year: string,
     *     birth_month: int,
     *     birth_day: int,
     *     gender: string,
     *     belfiore_code: string,
     *     birth_date: string,
     *     birth_place: ?string,
     *     is_foreign: bool,
     *     omocodia_level: int,
     * }|null
     */
    public function parse(string $fiscalCode): ?array
    {
        $fiscalCode = strtoupper(trim($fiscalCode));

        if (strlen($fiscalCode) !== 16) {
            return null;
        }

        $omocodiaLevel = $this->detectOmocodiaLevel($fiscalCode);
        $normalized = $this->normalizeOmocodia($fiscalCode);

        $surnameCode = substr($normalized, 0, 3);
        $firstnameCode = substr($normalized, 3, 3);

        $yearPart = substr($normalized, 6, 2);
        $monthChar = $normalized[8];
        $dayPart = (int) substr($normalized, 9, 2);

        $monthMap = array_flip(self::MONTH_CODES);
        $month = $monthMap[$monthChar] ?? null;

        if ($month === null) {
            return null;
        }

        $gender = 'M';
        $day = $dayPart;
        if ($dayPart > 40) {
            $gender = 'F';
            $day = $dayPart - 40;
        }

        $belfioreCode = substr($normalized, 11, 4);
        $isForeign = str_starts_with($belfioreCode, 'Z');

        // Determine full year (heuristic: > current 2-digit year → 1900s, else 2000s)
        $yearInt = (int) $yearPart;
        $currentTwoDigit = (int) date('y');
        $fullYear = $yearInt > $currentTwoDigit ? 1900 + $yearInt : 2000 + $yearInt;

        $birthDate = sprintf('%04d-%02d-%02d', $fullYear, $month, $day);

        // Resolve birth place name
        $birthPlace = $this->resolveBirthPlace($belfioreCode);

        return [
            'surname_code' => $surnameCode,
            'firstname_code' => $firstnameCode,
            'birth_year' => (string) $fullYear,
            'birth_month' => $month,
            'birth_day' => $day,
            'gender' => $gender,
            'belfiore_code' => $belfioreCode,
            'birth_date' => $birthDate,
            'birth_place' => $birthPlace,
            'is_foreign' => $isForeign,
            'omocodia_level' => $omocodiaLevel,
        ];
    }

    /**
     * Encode surname to 3-character fiscal code part.
     */
    private function encodeSurname(string $surname): string
    {
        $surname = $this->cleanString($surname);
        $consonants = $this->extractConsonants($surname);
        $vowels = $this->extractVowels($surname);

        $code = $consonants . $vowels;

        return strtoupper(str_pad(substr($code, 0, 3), 3, 'X'));
    }

    /**
     * Encode firstname to 3-character fiscal code part.
     * Special rule: if 4+ consonants, take 1st, 3rd, 4th.
     */
    private function encodeFirstname(string $firstname): string
    {
        $firstname = $this->cleanString($firstname);
        $consonants = $this->extractConsonants($firstname);
        $vowels = $this->extractVowels($firstname);

        if (strlen($consonants) >= 4) {
            $code = $consonants[0] . $consonants[2] . $consonants[3];
        } else {
            $code = $consonants . $vowels;
            $code = substr($code, 0, 3);
        }

        return strtoupper(str_pad($code, 3, 'X'));
    }

    /**
     * Resolve Belfiore code from input data.
     */
    private function resolveBelfioreCode(array $data): string
    {
        if (! empty($data['belfiore_code'])) {
            return strtoupper($data['belfiore_code']);
        }

        // Foreign country
        if (! empty($data['birth_country'])) {
            $country = Country::query()
                ->where('name', 'LIKE', $data['birth_country'])
                ->first();

            if ($country) {
                return $country->belfiore_code;
            }
        }

        // Italian city
        if (! empty($data['birth_city'])) {
            $query = City::query()
                ->where('name', 'LIKE', $data['birth_city']);

            if (! empty($data['birth_province'])) {
                $query->byProvince($data['birth_province']);
            }

            $city = $query->first();

            if ($city) {
                return $city->belfiore_code;
            }
        }

        throw new \InvalidArgumentException(
            'Unable to resolve Belfiore code. Provide belfiore_code, birth_city+birth_province, or birth_country.'
        );
    }

    /**
     * Calculate the check character (16th character) of a fiscal code.
     */
    private function calculateCheckCharacter(string $first15): string
    {
        $first15 = strtoupper($first15);
        $sum = 0;

        for ($i = 0; $i < 15; $i++) {
            $char = $first15[$i];
            // Positions are 1-indexed: odd = 1,3,5... even = 2,4,6...
            if (($i + 1) % 2 !== 0) {
                $sum += self::ODD_VALUES[$char] ?? 0;
            } else {
                $sum += self::EVEN_VALUES[$char] ?? 0;
            }
        }

        $remainder = $sum % 26;

        return chr(65 + $remainder); // A=65
    }

    /**
     * Normalize a fiscal code by reverting omocodia substitutions.
     */
    private function normalizeOmocodia(string $fiscalCode): string
    {
        $chars = str_split(strtoupper($fiscalCode));

        foreach (self::OMOCODIA_POSITIONS as $pos) {
            if (isset($chars[$pos]) && isset(self::OMOCODIA_REVERSE[$chars[$pos]])) {
                $chars[$pos] = self::OMOCODIA_REVERSE[$chars[$pos]];
            }
        }

        return implode('', $chars);
    }

    /**
     * Detect the omocodia level (number of digit→letter substitutions).
     */
    private function detectOmocodiaLevel(string $fiscalCode): int
    {
        $level = 0;
        $chars = str_split(strtoupper($fiscalCode));

        foreach (self::OMOCODIA_POSITIONS as $pos) {
            if (isset($chars[$pos]) && isset(self::OMOCODIA_REVERSE[$chars[$pos]])) {
                $level++;
            }
        }

        return $level;
    }

    /**
     * Resolve a birth place name from Belfiore code.
     */
    private function resolveBirthPlace(string $belfioreCode): ?string
    {
        if (str_starts_with($belfioreCode, 'Z')) {
            $country = Country::query()->where('belfiore_code', $belfioreCode)->first();
            return $country?->name;
        }

        $city = City::query()->where('belfiore_code', $belfioreCode)->first();
        return $city?->name;
    }

    /**
     * Remove non-alphabetic characters and accents.
     */
    private function cleanString(string $str): string
    {
        // Normalize accented characters
        $str = strtr($str, [
            'à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i',
            'ò' => 'o', 'ù' => 'u',
            'À' => 'A', 'È' => 'E', 'É' => 'E', 'Ì' => 'I',
            'Ò' => 'O', 'Ù' => 'U',
        ]);

        return preg_replace('/[^a-zA-Z]/', '', $str);
    }

    private function extractConsonants(string $str): string
    {
        return preg_replace('/[aeiouAEIOU]/', '', $str);
    }

    private function extractVowels(string $str): string
    {
        return preg_replace('/[^aeiouAEIOU]/', '', $str);
    }
}
