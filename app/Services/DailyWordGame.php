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

    /**
     * Day zero of the puzzle sequence. Changing it (or the shuffle seed)
     * reshuffles the entire schedule.
     */
    private const EPOCH = '2025-01-01';

    private const SHUFFLE_SEED = 727272;

    /** @var list<int>|null Lazily-built shuffled index order. */
    private ?array $order = null;

    public function targetForDate(DateTimeInterface $date): string
    {
        // Walk a seeded shuffle of the pool by day number so every word is
        // used exactly once per cycle. The old crc32(date) % count picker
        // clustered badly: two years of dates reached only ~60% of the pool
        // and repeated words within days of each other.
        $epoch = new \DateTimeImmutable(self::EPOCH);
        $days = (int) $epoch->diff(new \DateTimeImmutable($date->format('Y-m-d')))->format('%r%a');
        $count = count($this->words);
        $index = (($days % $count) + $count) % $count;

        return $this->words[$this->shuffledOrder()[$index]];
    }

    /**
     * Deterministic Fisher–Yates over the word indexes: identical on every
     * request and server, so the daily word never depends on process state.
     *
     * @return list<int>
     */
    private function shuffledOrder(): array
    {
        if ($this->order !== null) {
            return $this->order;
        }

        $order = range(0, count($this->words) - 1);
        mt_srand(self::SHUFFLE_SEED);
        for ($i = count($order) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
        }
        mt_srand(); // restore unpredictable seeding for everyone else

        return $this->order = $order;
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
