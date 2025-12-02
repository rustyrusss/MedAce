<?php

class ChatAPI
{
    private $apiKey;
    private $apiUrl = "https://api.openai.com/v1/chat/completions";
    private $model  = "gpt-5-nano"; // gpt-5-nano does NOT exist

    public function __construct()
    {
        $this->apiKey = getenv("API_KEY");

        if (!$this->apiKey) {
            throw new Exception("API_KEY is missing from environment variables.");
        }
    }

    public function sendMessage($userMessage, $systemMessage = "You are a helpful assistant.")
    {
        $payload = [
            "model" => $this->model,
            "messages" => [
                ["role" => "system", "content" => $systemMessage],
                ["role" => "user", "content" => $userMessage]
            ],
            "max_tokens" => 500
        ];

        $ch = curl_init($this->apiUrl);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer {$this->apiKey}"
            ],
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response) {
            return ["error" => "No response from OpenAI API"];
        }

        $json = json_decode($response, true);

        // OpenAI error
        if (isset($json["error"])) {
            return ["error" => $json["error"]["message"]];
        }

        // SUCCESS — match EXACT format your JS expects
        if (isset($json["choices"][0]["message"]["content"])) {

            $content = trim(strip_tags($json["choices"][0]["message"]["content"]));

            return [
                "choices" => [
                    [
                        "message" => [
                            "content" => $content
                        ]
                    ]
                ]
            ];
        }

        // fallback invalid format
        return [
            "error" => "Invalid OpenAI API response format.",
            "raw" => $json,
            "http_code" => $httpCode
        ];
    }
}
