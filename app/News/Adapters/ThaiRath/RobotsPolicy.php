<?php

namespace App\News\Adapters\ThaiRath;

use App\News\Exceptions\SourceFetchException;
use App\News\Http\SourceHttpClient;
use Illuminate\Support\Facades\Cache;

class RobotsPolicy
{
    public const USER_AGENT = 'NewsFlow';

    public function __construct(private readonly SourceHttpClient $http) {}

    public function assertAllowed(string $url, string $sourceKey): void
    {
        $robotsUrl = 'https://www.thairath.co.th/robots.txt';
        $rules = Cache::remember('robots:thairath:'.self::USER_AGENT, now()->addHour(), fn () => $this->http->get($robotsUrl, $sourceKey, allowNotFound: true));
        if (! $this->isAllowed($url, $rules)) {
            throw new SourceFetchException('robots_disallowed', 'The source robots.txt disallows this path.');
        }
    }

    private function isAllowed(string $url, string $robots): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $groups = [];
        $agents = [];
        $activeGroup = false;
        $groupHasDirective = false;
        foreach (preg_split('/\r?\n/', $robots) ?: [] as $line) {
            $line = trim(explode('#', $line, 2)[0]);
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);
            if ($field === 'user-agent') {
                if ($activeGroup && $groupHasDirective) {
                    $agents = [];
                    $activeGroup = false;
                    $groupHasDirective = false;
                }
                $agents[] = strtolower($value);
                $activeGroup = true;

                continue;
            }
            if (in_array($field, ['allow', 'disallow'], true) && $activeGroup) {
                $groupHasDirective = true;
                if ($value !== '') {
                    foreach ($agents as $agent) {
                        $groups[$agent][] = [$field, $value];
                    }
                }
            }
        }

        $rules = $groups[strtolower(self::USER_AGENT)] ?? $groups['*'] ?? [];
        $winner = null;
        foreach ($rules as [$type, $pattern]) {
            $regex = '#^'.str_replace('\\*', '.*', preg_quote($pattern, '#')).(str_ends_with($pattern, '$') ? '' : '.*').'#u';
            if (preg_match($regex, $path) && ($winner === null || strlen($pattern) > strlen($winner[1]) || (strlen($pattern) === strlen($winner[1]) && $type === 'allow'))) {
                $winner = [$type, $pattern];
            }
        }

        return $winner === null || $winner[0] === 'allow';
    }
}
