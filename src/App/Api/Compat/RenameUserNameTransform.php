<?php

declare(strict_types=1);

namespace BikeShare\App\Api\Compat;

class RenameUserNameTransform implements ApiResponseTransformInterface
{
    private const RENAMES = [
        'userName' => 'username',
    ];

    public function getSinceVersion(): string
    {
        return '1.0.1';
    }

    public function getRoutes(): array
    {
        return [
            'api_v1_admin_users',
            'api_v1_admin_user_item',
            'api_v1_admin_bikes',
            'api_v1_bike_item',
            'api_v1_bike_last_usage',
            'api_v1_admin_report_users',
        ];
    }

    public function transform(array $data): array
    {
        return $this->renameKeysRecursive($data);
    }

    private function renameKeysRecursive(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $newKey = self::RENAMES[$key] ?? $key;
            $result[$newKey] = is_array($value) ? $this->renameKeysRecursive($value) : $value;
        }

        return $result;
    }
}
