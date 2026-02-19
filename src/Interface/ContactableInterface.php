<?php

namespace App\Interface;

interface ContactableInterface
{
    public function getName(): string;

    public function getContactAddress(): ?string;

    public function getContactZipcode(): ?string;

    public function getContactCity(): ?string;

    public function getContactCountry(): ?string;
}
