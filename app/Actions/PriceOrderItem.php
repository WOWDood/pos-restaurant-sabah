<?php

namespace App\Actions;

use App\Models\Item;
use App\Models\ModifierGroup;

class PriceOrderItem
{
    /**
     * @param  array<int, mixed>  $selections
     * @return array{unit_price: float, modifiers: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public function calculate(Item $item, array $selections): array
    {
        $unitPrice = (float) $item->unit_price;
        $modifiers = [];
        $errors = [];

        foreach ($item->modifierGroups as $group) {
            $selectedIds = $this->selectedOptionIds($group, $selections);
            $count = count($selectedIds);
            if ($count < $group->min_select) {
                $errors[$group->id] = "{$group->name}: choose at least {$group->min_select} option(s).";
            } elseif ($count > $group->max_select) {
                $errors[$group->id] = "{$group->name}: choose at most {$group->max_select} option(s).";
            }

            foreach ($group->options as $option) {
                if (! in_array((int) $option->id, $selectedIds, true)) {
                    continue;
                }
                $unitPrice += (float) $option->price_adjustment;
                $modifiers[] = [
                    'group_id' => $group->id, 'group_name' => $group->name,
                    'option_id' => $option->id, 'name_en' => $option->name_en,
                    'name_zh' => $option->name_zh, 'price_adjustment' => (float) $option->price_adjustment,
                ];
            }
        }

        return ['unit_price' => round($unitPrice, 2), 'modifiers' => $modifiers, 'errors' => $errors];
    }

    /**
     * @param  array<int, mixed>  $selections
     * @return array<int, int>
     */
    public function selectedOptionIds(ModifierGroup $group, array $selections): array
    {
        $raw = $selections[$group->id] ?? [];
        $raw = is_array($raw) ? $raw : [$raw];
        $validIds = $group->options->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_unique(array_intersect(array_map('intval', $raw), $validIds)));
    }
}
