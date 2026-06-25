<?php

namespace App\Services;

use DateTimeInterface;
use InvalidArgumentException;

class DailyWordGame
{
    /** @var list<string> */
    private array $words;

    public function __construct()
    {
        $this->words = config('dwg.words', []);

        if (empty($this->words)) {
            throw new \RuntimeException('Daily word list is empty.');
        }
    }

    public function wordLength(): int
    {
        return 5;
    }

    public function maxGuesses(): int
    {
        return 6;
    }

    public function targetForDate(DateTimeInterface $date): string
    {
        $key = $date->format('Y-m-d');
        $index = abs(crc32($key)) % count($this->words);

        return $this->words[$index];
    }

    public function isValidWord(string $word): bool
    {
        $word = strtolower($word);

        if (strlen($word) !== $this->wordLength()) {
            return false;
        }

        if (! ctype_alpha($word)) {
            return false;
        }

        return in_array($word, $this->words, true);
    }

    /**
     * Evaluate a guess against the target.
     *
     * @return list<string> One of 'correct', 'present', 'absent' per letter.
     */
    public function evaluate(string $guess, string $target): array
    {
        $guess = strtolower($guess);
        $target = strtolower($target);

        if (strlen($guess) !== $this->wordLength() || strlen($target) !== $this->wordLength()) {
            throw new InvalidArgumentException('Guess and target must be 5 letters.');
        }

        $result = array_fill(0, $this->wordLength(), 'absent');
        $targetLetters = str_split($target);
        $guessLetters = str_split($guess);

        // First pass: exact matches.
        foreach ($guessLetters as $i => $letter) {
            if ($targetLetters[$i] === $letter) {
                $result[$i] = 'correct';
                $targetLetters[$i] = null;
                $guessLetters[$i] = null;
            }
        }

        // Second pass: present but misplaced.
        foreach ($guessLetters as $i => $letter) {
            if ($letter === null) {
                continue;
            }

            $pos = array_search($letter, $targetLetters, true);
            if ($pos !== false) {
                $result[$i] = 'present';
                $targetLetters[$pos] = null;
            }
        }

        return $result;
    }
}
