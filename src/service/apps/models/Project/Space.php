<?php

namespace Project;

class SpaceModel
{
    public static function parseBranch(array $branch = [], string $separator = '-')
    {
        if (!$branch) return [];
        $info = [
            'space_id_building'             => '',
            'space_name_building'           => '',
            'space_name_exclude_building'   => '',
            'space_name_full'               => '',
        ];
        $i = count($branch);
        foreach ($branch ?: [] as $k => $space) {
            if ($space['space_type'] === 244) {
                $i = $k;
                $info['space_id_building'] = $space['space_id'];
                $info['space_name_building'] = $space['space_name'];
                break;
            }
        }
        $names = array_reverse(array_column($branch, 'space_name'));
        $info['space_name_exclude_building'] = implode($separator, array_slice($names, count($branch) - $i));
        $info['space_name_full'] = implode($separator, $names);
        return $info;
    }
}

