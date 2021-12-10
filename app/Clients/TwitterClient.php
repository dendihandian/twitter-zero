<?php

namespace App\Clients;

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
}