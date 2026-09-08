<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Sesi yang sudah selesai tidak menerima jawaban baru. */
final class SessionCompletedException extends RuntimeException {}
