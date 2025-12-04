<?php

class ChatAPI
{
    private $apiKey;
    private $apiUrl = "https://api.openai.com/v1/responses"; 
    private $model  = "gpt-3.5-turbo"; // stable & cheap for chatbot

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
            "input" => [
                ["role" => "system", "content" => $systemMessage],
                ["role" => "user", "content" => $userMessage]
            ],
            "max_output_tokens" => 500
        ];

        $ch = curl_init($this->apiUrl);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: " . "Bearer " . $this->apiKey
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

        // API error
        if (isset($json["error"])) {
            return ["error" => $json["error"]["message"]];
        }

        // SUCCESS — match EXACT format your JS expects
        if (isset($json["output_text"])) {

            $content = trim(strip_tags($json["output_text"]));

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

    // -----------------------------------------------------
    // READY-TO-INTEGRATE: QUIZ GENERATOR
    // -----------------------------------------------------

    public function generateQuiz($moduleContent)
    {
        $prompt = "
        Create a 5-question multiple choice quiz based on this module:

        $moduleContent

        Respond in JSON:
        [
            {
                \"question\": \"...\",
                \"choices\": [\"A\", \"B\", \"C\", \"D\"],
                \"answer\": \"B\"
            }
        ]
        ";

        return $this->sendMessage($prompt);
    }

    // -----------------------------------------------------
    // READY-TO-INTEGRATE: FLASHCARD GENERATOR
    // -----------------------------------------------------

    public function generateFlashcards($moduleContent)
    {
        $prompt = "
        Create 10 flashcards from this module.

        Respond in JSON:
        [
            {\"front\": \"...\", \"back\": \"...\"}
        ]
        ";

        return $this->sendMessage($prompt);
    }

    // -----------------------------------------------------
    // READY-TO-INTEGRATE: PROGRESS SUMMARY MESSAGE
    // -----------------------------------------------------

    public function generateProgressMessage($progressData)
    {
        $progressText = json_encode($progressData, JSON_PRETTY_PRINT);

        $prompt = "
        Summarize this student's progress in a friendly tone:

        $progressText
        ";

        return $this->sendMessage($prompt);
    }
}
