<?php

namespace App\Services;

use App\Models\Wallet;
use Illuminate\Support\Str;

class VopService
{
    public function verify(
        Wallet $wallet,
        string $providedName
    ): array {

        if (! $wallet->isActive()) {

            return [
                'status' => 'unavailable',
                'message'
                    => 'Recipient verification is currently unavailable.',
            ];
        }

        $actual =
            $this->normalize(
                $wallet->user->name
            );

        $provided =
            $this->normalize(
                $providedName
            );

        if ($actual === $provided) {

            return [
                'status' => 'match',
                'message'
                    => 'Recipient name matches.',
            ];
        }

        $actualTokens =
            preg_split(
                '/\s+/u',
                $actual
            ) ?: [];

        $providedTokens =
            preg_split(
                '/\s+/u',
                $provided
            ) ?: [];

        $commonTokens =
            array_intersect(
                $actualTokens,
                $providedTokens
            );

        if (count($commonTokens) >= 1) {

            return [
                'status' => 'close_match',

                'message'
                    => 'Recipient name is similar but not identical.',

                'suggested_name'
                    => $wallet->user->name,
            ];
        }

        return [
            'status' => 'no_match',

            'message'
                => 'Recipient name does not match.',
        ];
    }

    private function normalize(
        string $name
    ): string {

        return (string)
            Str::of($name)
                ->lower()
                ->squish();
    }
}