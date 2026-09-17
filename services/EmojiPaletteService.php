<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\services;

use Yii;

/** Provides HumHub's bundled complete Unicode emoji catalogue in categories. */
final class EmojiPaletteService
{
    /** @return array<int, array{slug:string,name:string,icon:string,emojis:array<int, array{emoji:string,name:string}>}> */
    public function categories(): array
    {
        $path = Yii::getAlias('@npm/unicode-emoji-json/data-by-group.json');
        if (!is_file($path)) {
            return [];
        }

        $icons = [
            'smileys_emotion' => '☺',
            'people_body' => '👋',
            'animals_nature' => '🐻',
            'food_drink' => '🍔',
            'travel_places' => '🚗',
            'activities' => '⚽',
            'objects' => '💡',
            'symbols' => '🔣',
            'flags' => '🏳️',
        ];
        $labels = [
            'smileys_emotion' => 'Emojis & Personen',
            'people_body' => 'Personen & Körper',
            'animals_nature' => 'Tiere & Natur',
            'food_drink' => 'Essen & Trinken',
            'travel_places' => 'Reisen & Orte',
            'activities' => 'Aktivitäten',
            'objects' => 'Objekte',
            'symbols' => 'Symbole',
            'flags' => 'Flaggen',
        ];

        $categories = [];
        foreach ((array) json_decode((string) file_get_contents($path), true) as $category) {
            $slug = (string) ($category['slug'] ?? '');
            $emojis = [];
            foreach ((array) ($category['emojis'] ?? []) as $emoji) {
                if (!isset($emoji['emoji'], $emoji['name'])) {
                    continue;
                }
                $emojis[] = ['emoji' => (string) $emoji['emoji'], 'name' => (string) $emoji['name']];
            }
            if ($slug !== '' && $emojis !== []) {
                $categories[] = [
                    'slug' => $slug,
                    'name' => $labels[$slug] ?? (string) ($category['name'] ?? $slug),
                    'icon' => $icons[$slug] ?? '•',
                    'emojis' => $emojis,
                ];
            }
        }

        return $categories;
    }

    public function contains(string $candidate): bool
    {
        foreach ($this->categories() as $category) {
            foreach ($category['emojis'] as $emoji) {
                if ($emoji['emoji'] === $candidate) {
                    return true;
                }
            }
        }

        return false;
    }
}
