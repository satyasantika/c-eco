<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Tes rombongan belum mencapai starts_at menurut jam server (R6). */
class ExamNotStartedException extends RuntimeException {}
