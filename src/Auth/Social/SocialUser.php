<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 17 — Social Auth: SocialUser DTO | 2026-04-06

namespace Nemesis\Auth\Social;

readonly class SocialUser
{
    public function __construct(
        public string  $id,
        public string  $name,
        public string  $email,
        public string  $avatar,
        public string  $token,
        public string  $provider,
        public array   $raw = [],
    ) {}

    public static function fromArray(array $data, string $provider, string $token): self
    {
        return new self(
            id:       (string) ($data['id']     ?? $data['sub'] ?? ''),
            name:     (string) ($data['name']   ?? $data['login'] ?? ''),
            email:    (string) ($data['email']  ?? ''),
            avatar:   (string) ($data['avatar_url'] ?? $data['picture'] ?? $data['profile_image_url'] ?? ''),
            token:    $token,
            provider: $provider,
            raw:      $data,
        );
    }

    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'email'    => $this->email,
            'avatar'   => $this->avatar,
            'token'    => $this->token,
            'provider' => $this->provider,
        ];
    }
}
