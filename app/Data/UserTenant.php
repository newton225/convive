<?php

namespace App\Data;

readonly class UserTenant
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public bool $isPersonal,
        public ?int $profileId,
        public ?string $profileName,
        public bool $isOwner = false,
        public ?bool $isCurrent = null,
    ) {
        //
    }
}
