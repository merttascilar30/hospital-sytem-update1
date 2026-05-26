<?php
/**
 * Allowed patient security questions (key => label).
 */
declare(strict_types=1);

function hs_security_questions(): array
{
    return [
        'mother_maiden' => "What is your mother's maiden name?",
        'first_pet'     => 'What was the name of your first pet?',
        'favorite_book' => 'What is your favorite book?',
    ];
}

function hs_security_question_label(string $key): string
{
    $questions = hs_security_questions();
    return $questions[$key] ?? '';
}

function hs_is_valid_security_question(string $key): bool
{
    return array_key_exists($key, hs_security_questions());
}

/** Normalize answer for case-insensitive comparison at registration verification time. */
function hs_normalize_security_answer(string $answer): string
{
    return mb_strtolower(trim($answer), 'UTF-8');
}

function hs_hash_security_answer(string $answer): string
{
    return password_hash(hs_normalize_security_answer($answer), PASSWORD_DEFAULT);
}

function hs_verify_security_answer(string $answer, string $storedHash): bool
{
    return password_verify(hs_normalize_security_answer($answer), $storedHash);
}
