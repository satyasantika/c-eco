<?php

declare(strict_types=1);

namespace App\CAT;

use RuntimeException;

/** Bank habis: tidak ada butir tersisa yang boleh disajikan pada sesi ini. */
final class NoCandidateException extends RuntimeException {}
