<?php

/**
 * api reference: https://developer.twitter.com/en/docs/api-reference-index
 */

namespace App\Clients;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class TwitterClient
{
    protected $client;
    protected $config;

    public function __construct()
    {
        $this->config = config('twitter');

        $this->client = Http::baseUrl("https://api.twitter.com/2")
            ->withHeaders([
                'Authorization' => implode(" ", ['Bearer', $this->config['bearer_token']])
            ]);
    }

    public function userLookupByUsername(string $username)
    {
        if (!$username) throw new Exception('Username required');

        $key = implode('.', ['users', $username]);
        $json = Redis::get($key);

        if (!$json) {
            $response = $this->client->get("users/by/username/{$username}?expansions=pinned_tweet_id&user.fields=created_at&tweet.fields=created_at");

            if ($response->ok()) {
                $json = json_encode($response->json());
                Redis::set($key, $json);
            }
        }

        return json_decode($json, true);
    }

    public function userTimelineByUsername(string $username)
    {
        if (!$username) throw new Exception('Username required');

        $key = implode('.', ['users', $username, 'timeline']);
        $json = Redis::get($key);

        if (!$json) {
            $userId = $this->userLookupByUsername($username)['data']['id'];
            $response = $this->client->get("users/{$userId}/tweets");

            if ($response->ok()) {
                $json = json_encode($response->json());
                Redis::set($key, $json);
            }
        }

        return json_decode($json, true);
    }

    public function tweet(string $text)
    {
        if (!$text) throw new Exception('Text required');

        $response = $this->client->post("tweets", [
            'text' => $text
        ]);

        if ($response->failed()) {
            return false;
        }

        return true;
    }
}