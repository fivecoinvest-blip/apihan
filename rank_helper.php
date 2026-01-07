<?php
/**
 * User Ranking System Based on Total Wagered
 */

class UserRank {
    // Rank thresholds in PHP (base currency)
    const RANKS = [
        'Emperor' => 35000000,
        'King' => 20000001,
        'Duke' => 12000001,
        'Noble' => 8000001,
        'Knight' => 5000001,
        'Elite' => 3000001,
        'Diamond' => 1500001,
        'Platinum' => 700001,
        'Gold' => 300001,
        'Silver' => 100001,
        'Bronze' => 0,
    ];

    // Rank colors
    const RANK_COLORS = [
        'Emperor' => '#ff00ff',  // Magenta
        'King' => '#ffd700',     // Gold
        'Duke' => '#e6e6fa',     // Lavender
        'Noble' => '#9370db',    // Medium Purple
        'Knight' => '#4169e1',   // Royal Blue
        'Elite' => '#00ced1',    // Dark Turquoise
        'Diamond' => '#00ffff',  // Cyan
        'Platinum' => '#c0c0c0', // Silver/Platinum
        'Gold' => '#ffa500',     // Orange Gold
        'Silver' => '#a8a8a8',   // Light Gray
        'Bronze' => '#cd7f32',   // Bronze
    ];

    // Rank icons/emojis
    const RANK_ICONS = [
        'Emperor' => '👑',
        'King' => '🤴',
        'Duke' => '🎩',
        'Noble' => '🎖️',
        'Knight' => '⚔️',
        'Elite' => '💎',
        'Diamond' => '💠',
        'Platinum' => '⭐',
        'Gold' => '🥇',
        'Silver' => '🥈',
        'Bronze' => '🥉',
    ];

    /**
     * Get user rank based on total wagered amount
     */
    public static function getRank($totalWagered) {
        foreach (self::RANKS as $rank => $threshold) {
            if ($totalWagered >= $threshold) {
                return $rank;
            }
        }
        return 'Bronze'; // Default
    }

    /**
     * Get rank color
     */
    public static function getRankColor($rank) {
        return self::RANK_COLORS[$rank] ?? '#cd7f32';
    }

    /**
     * Get rank icon
     */
    public static function getRankIcon($rank) {
        return self::RANK_ICONS[$rank] ?? '🥉';
    }

    /**
     * Get next rank information
     */
    public static function getNextRank($totalWagered) {
        $currentRank = self::getRank($totalWagered);
        $rankKeys = array_keys(self::RANKS);
        $currentIndex = array_search($currentRank, $rankKeys);
        
        if ($currentIndex === false || $currentIndex === 0) {
            return null; // Already at max rank
        }
        
        $nextRank = $rankKeys[$currentIndex - 1];
        $nextThreshold = self::RANKS[$nextRank];
        $remaining = $nextThreshold - $totalWagered;
        
        return [
            'rank' => $nextRank,
            'threshold' => $nextThreshold,
            'remaining' => $remaining,
            'progress' => ($totalWagered / $nextThreshold) * 100
        ];
    }

    /**
     * Get rank badge HTML
     */
    public static function getRankBadge($rank, $size = 'medium') {
        $color = self::getRankColor($rank);
        $icon = self::getRankIcon($rank);
        
        $sizes = [
            'small' => ['padding' => '4px 8px', 'font-size' => '11px'],
            'medium' => ['padding' => '6px 12px', 'font-size' => '13px'],
            'large' => ['padding' => '8px 16px', 'font-size' => '15px']
        ];
        
        $style = $sizes[$size] ?? $sizes['medium'];
        
        return sprintf(
            '<span style="display: inline-flex; align-items: center; gap: 4px; background: linear-gradient(135deg, %s, %s); color: white; padding: %s; border-radius: 6px; font-weight: 600; font-size: %s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">%s %s</span>',
            $color,
            self::adjustBrightness($color, -20),
            $style['padding'],
            $style['font-size'],
            $icon,
            htmlspecialchars($rank)
        );
    }

    /**
     * Get all ranks with ranges
     */
    public static function getAllRanks() {
        $ranks = [];
        $rankKeys = array_keys(self::RANKS);
        
        for ($i = count($rankKeys) - 1; $i >= 0; $i--) {
            $rank = $rankKeys[$i];
            $min = self::RANKS[$rank];
            $max = ($i > 0) ? self::RANKS[$rankKeys[$i - 1]] - 1 : PHP_INT_MAX;
            
            $ranks[] = [
                'name' => $rank,
                'min' => $min,
                'max' => $max,
                'color' => self::getRankColor($rank),
                'icon' => self::getRankIcon($rank)
            ];
        }
        
        return $ranks;
    }

    /**
     * Adjust color brightness
     */
    public static function adjustBrightness($hex, $steps) {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, min(255, $r + $steps));
        $g = max(0, min(255, $g + $steps));
        $b = max(0, min(255, $b + $steps));
        
        return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT)
                  . str_pad(dechex($g), 2, '0', STR_PAD_LEFT)
                  . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Format rank range
     */
    public static function formatRankRange($min, $max) {
        if ($max === PHP_INT_MAX) {
            return '₱' . number_format($min) . '+';
        }
        return '₱' . number_format($min) . ' – ₱' . number_format($max);
    }
}
