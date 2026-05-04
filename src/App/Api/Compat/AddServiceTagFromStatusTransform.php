<?php

declare(strict_types=1);

namespace BikeShare\App\Api\Compat;

use BikeShare\Enum\StandStatus;

class AddServiceTagFromStatusTransform implements ApiResponseTransformInterface
{
    public function getSinceVersion(): string
    {
        return '1.1.2';
    }

    public function getRoutes(): array
    {
        return [
            'api_v1_stand_markers',
            'api_v1_stands',
            'api_v1_admin_stand_item',
        ];
    }

    public function transform(array $data): array
    {
        return $this->addServiceTagRecursive($data);
    }

    private function addServiceTagRecursive(array $data): array
    {
        if (isset($data['status']) && is_string($data['status'])) {
            $status = StandStatus::tryFrom($data['status']);
            $data['serviceTag'] = ($status === StandStatus::TECHNICAL || $status === StandStatus::HIDDEN) ? 1 : 0;
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->addServiceTagRecursive($value);
            }
        }

        return $data;
    }
}
