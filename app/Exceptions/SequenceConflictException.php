<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Jawaban berbeda dikirim untuk sequence yang sudah dijawab (aturan R3).
 * Dipetakan ke HTTP 409 oleh lapisan API.
 */
final class SequenceConflictException extends RuntimeException {}
