<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Business-rule failures when deleting or changing the role of a director
 * who still has projects assigned via proyectos.director_id.
 */
final class DirectorAssignmentException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
        public readonly string $errorCode = 'director_has_projects',
        public readonly int $projectCount = 0,
        public readonly bool $canReassign = false,
    ) {
        parent::__construct($message);
    }

    public static function hasProjects(string $message, int $projectCount, bool $canReassign): self
    {
        return new self($message, 422, 'director_has_projects', $projectCount, $canReassign);
    }

    public static function noDirectorsAvailable(string $message): self
    {
        return new self($message, 422, 'no_directors_available');
    }

    public static function notADirector(string $message): self
    {
        return new self($message, 422, 'not_a_director');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'error' => $this->errorCode,
            'message' => $this->getMessage(),
        ];

        if ($this->errorCode === 'director_has_projects') {
            $payload['proyectos_count'] = $this->projectCount;
            $payload['can_reassign'] = $this->canReassign;
        }

        return $payload;
    }
}
