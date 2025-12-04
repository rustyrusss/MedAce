<?php
/**
 * Enhanced ChatAPI with Lesson-Based Flashcard Generation & Progress Tracking
 * UPDATED: Fixed getModulesForStudent to use correct table structure
 */

class ChatAPI
{
    private $apiKey;
    private $apiUrl = "https://api.openai.com/v1/chat/completions";
    private $model = "gpt-3.5-turbo";

    public function __construct()
    {
        // Try multiple sources for API key
        $this->apiKey = getenv("API_KEY") ?: ($_ENV["API_KEY"] ?? ($_SERVER["API_KEY"] ?? null));
        
        if (!$this->apiKey) {
            $this->apiKey = getenv("OPENAI_API_KEY") ?: ($_ENV["OPENAI_API_KEY"] ?? ($_SERVER["OPENAI_API_KEY"] ?? null));
        }

        // Manual .env load if still not found
        if (!$this->apiKey && file_exists(__DIR__ . '/../.env')) {
            $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || $line[0] === '#') continue;
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v, " \t\n\r\0\x0B\"'");
                    if ($k === 'API_KEY' || $k === 'OPENAI_API_KEY') {
                        $this->apiKey = $v;
                        break;
                    }
                }
            }
        }

        if (!$this->apiKey) {
            throw new Exception("API_KEY is missing from environment variables.");
        }
    }

    /**
     * Send message to OpenAI with proper formatting
     */
    public function sendMessage($userMessage, $systemMessage = "You are a helpful assistant.", $maxTokens = 500)
    {
        $payload = [
            "model" => $this->model,
            "messages" => [
                ["role" => "system", "content" => $systemMessage],
                ["role" => "user", "content" => $userMessage]
            ],
            "max_tokens" => $maxTokens,
            "temperature" => 0.8
        ];

        $ch = curl_init($this->apiUrl);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ["error" => "Connection failed: " . $error];
        }

        if (!$response) {
            return ["error" => "No response from OpenAI API"];
        }

        $json = json_decode($response, true);

        if (!$json) {
            return ["error" => "Invalid JSON response"];
        }

        // API error
        if (isset($json["error"])) {
            return ["error" => $json["error"]["message"] ?? "OpenAI API error"];
        }

        // SUCCESS - Return in expected format
        if (isset($json["choices"]) && isset($json["choices"][0]["message"]["content"])) {
            return [
                "success" => true,
                "content" => trim($json["choices"][0]["message"]["content"]),
                "raw" => $json
            ];
        }

        // Fallback
        return [
            "error" => "Invalid OpenAI API response format.",
            "raw" => $json,
            "http_code" => $httpCode
        ];
    }

    /**
     * Get student progress data from database
     */
    private function getStudentProgress($conn, $studentId)
    {
        $data = [
            'total_modules' => 0,
            'completed_modules' => 0,
            'active_modules' => 0,
            'total_quizzes' => 0,
            'completed_quizzes' => 0,
            'passed_quizzes' => 0,
            'failed_quizzes' => 0,
            'average_score' => 0,
            'weak_areas' => [],
            'strong_areas' => []
        ];

        try {
            // FIXED: Use correct table name 'student_progress' instead of 'student_module_progress'
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as active
                FROM student_progress 
                WHERE student_id = ?
            ");
            $stmt->execute([$studentId]);
            $moduleStats = $stmt->fetch(PDO::FETCH_ASSOC);

            $data['total_modules'] = (int)$moduleStats['total'];
            $data['completed_modules'] = (int)$moduleStats['completed'];
            $data['active_modules'] = (int)$moduleStats['active'];

            // Get quiz stats
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) as passed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    AVG(CASE WHEN score IS NOT NULL THEN score ELSE 0 END) as avg_score
                FROM quiz_participation 
                WHERE student_id = ?
            ");
            $stmt->execute([$studentId]);
            $quizStats = $stmt->fetch(PDO::FETCH_ASSOC);

            $data['total_quizzes'] = (int)$quizStats['total'];
            $data['completed_quizzes'] = (int)$quizStats['completed'];
            $data['passed_quizzes'] = (int)$quizStats['passed'];
            $data['failed_quizzes'] = (int)$quizStats['failed'];
            $data['average_score'] = round((float)$quizStats['avg_score'], 1);

            // Identify weak areas (subjects with low average scores)
            $stmt = $conn->prepare("
                SELECT q.subject, AVG(qp.score) as avg_score
                FROM quiz_participation qp
                JOIN quizzes q ON q.id = qp.quiz_id
                WHERE qp.student_id = ? AND qp.score IS NOT NULL
                GROUP BY q.subject
                HAVING avg_score < 70
                ORDER BY avg_score ASC
                LIMIT 3
            ");
            $stmt->execute([$studentId]);
            $data['weak_areas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Identify strong areas
            $stmt = $conn->prepare("
                SELECT q.subject, AVG(qp.score) as avg_score
                FROM quiz_participation qp
                JOIN quizzes q ON q.id = qp.quiz_id
                WHERE qp.student_id = ? AND qp.score IS NOT NULL
                GROUP BY q.subject
                HAVING avg_score >= 80
                ORDER BY avg_score DESC
                LIMIT 3
            ");
            $stmt->execute([$studentId]);
            $data['strong_areas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            // Return default data if queries fail
        }

        return $data;
    }

    /**
     * Get available modules for student
     * FIXED: Match resources.php structure exactly
     */
    private function getStudentModules($conn, $studentId)
    {
        try {
            $stmt = $conn->prepare("
                SELECT 
                    m.id,
                    m.title,
                    m.description,
                    COALESCE(sp.status, 'Pending') AS status
                FROM modules m
                LEFT JOIN student_progress sp ON sp.module_id = m.id AND sp.student_id = ?
                WHERE m.status IN ('active', 'published')
                ORDER BY m.display_order ASC, m.created_at DESC
            ");
            $stmt->execute([$studentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get module content for flashcard generation
     */
    private function getModuleContent($conn, $moduleId)
    {
        try {
            $stmt = $conn->prepare("
                SELECT title, description, content, file_path
                FROM modules
                WHERE id = ?
            ");
            $stmt->execute([$moduleId]);
            $module = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$module) {
                return null;
            }

            // Build content string
            $content = "MODULE: " . $module['title'] . "\n\n";
            
            if (!empty($module['description'])) {
                $content .= "DESCRIPTION:\n" . $module['description'] . "\n\n";
            }

            if (!empty($module['content'])) {
                $content .= "CONTENT:\n" . $module['content'] . "\n\n";
            }

            // If there's a PDF file, extract text from it
            if (!empty($module['file_path']) && file_exists($module['file_path'])) {
                $extracted = $this->extractTextFromPDF($module['file_path']);
                if ($extracted) {
                    $content .= "ADDITIONAL CONTENT:\n" . $extracted;
                }
            }

            return $content;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Extract text from PDF (basic implementation)
     */
    private function extractTextFromPDF($filePath)
    {
        try {
            // Try using pdftotext if available
            if (function_exists('shell_exec')) {
                $output = shell_exec("pdftotext " . escapeshellarg($filePath) . " -");
                if ($output) {
                    return substr($output, 0, 5000); // Limit to 5000 chars
                }
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Build system prompt based on task
     */
    private function buildSystemPrompt($task, $studentName)
    {
        $baseName = $studentName ? " Student's name is $studentName." : "";

        switch ($task) {
            case "progress":
                return "You are MedAce AI Assistant, a helpful nursing tutor with access to the student's learning progress data.$baseName Your role is to provide personalized insights about their progress, identify strengths and areas for improvement, suggest next steps, and motivate them. Be encouraging and specific. Keep responses under 200 words.";

            case "flashcard":
                return "You are MedAce AI Assistant, a nursing education expert specialized in creating effective study flashcards.$baseName Create exactly 5 flashcards for the requested nursing topic. Format each flashcard as:

CARD [number]
Q: [Question - clear, specific, testing key concept]
A: [Answer - concise, accurate, includes key details]
---

Make questions challenging but fair. Focus on clinical application, not just memorization. Use proper nursing terminology.";

            default: // chat
                return "You are MedAce AI Assistant, a helpful nursing tutor.$baseName Be encouraging, concise (under 150 words), and use nursing terminology appropriately. If asked about progress, suggest they use the 'Check My Progress' button. If asked for flashcards, suggest they use the 'Generate Flashcards' button.";
        }
    }

    /**
     * Build user prompt with context
     */
    private function buildUserPrompt($task, $userMessage, $progressData, $lessonContent = null)
    {
        switch ($task) {
            case "progress":
                $context = "Current Progress Data:\n";
                $context .= "- Modules: {$progressData['completed_modules']}/{$progressData['total_modules']} completed\n";
                $context .= "- Quizzes: {$progressData['completed_quizzes']} completed ({$progressData['passed_quizzes']} passed, {$progressData['failed_quizzes']} failed)\n";
                $context .= "- Average Score: {$progressData['average_score']}%\n";

                if (!empty($progressData['weak_areas'])) {
                    $context .= "\nAreas needing improvement:\n";
                    foreach ($progressData['weak_areas'] as $area) {
                        $context .= "- {$area['subject']}: " . round($area['avg_score'], 1) . "% avg\n";
                    }
                }

                if (!empty($progressData['strong_areas'])) {
                    $context .= "\nStrong areas:\n";
                    foreach ($progressData['strong_areas'] as $area) {
                        $context .= "- {$area['subject']}: " . round($area['avg_score'], 1) . "% avg\n";
                    }
                }

                return $context . "\n" . $userMessage;

            case "flashcard":
                if ($lessonContent && !empty($lessonContent)) {
                    // Generate flashcards from lesson content
                    $prompt = "Based on the following lesson content, generate 5 nursing flashcards:\n\n";
                    $prompt .= "LESSON CONTENT:\n" . substr($lessonContent, 0, 3000) . "\n\n";
                    $prompt .= "Generate flashcards that test key concepts, clinical applications, and important facts from this lesson.";
                    return $prompt;
                } else {
                    return "Generate 5 nursing flashcards for: " . $userMessage;
                }

            default:
                return $userMessage;
        }
    }

    /**
     * Parse flashcards from AI response
     */
    private function parseFlashcards($content)
    {
        $flashcards = [];
        $cards = preg_split('/CARD\s+\d+/i', $content);

        foreach ($cards as $card) {
            $card = trim($card);
            if (empty($card)) continue;

            // Extract Q and A
            if (preg_match('/Q:\s*(.+?)(?=A:)/s', $card, $qMatch) &&
                preg_match('/A:\s*(.+?)(?=---|$)/s', $card, $aMatch)) {

                $flashcards[] = [
                    'question' => trim($qMatch[1]),
                    'answer' => trim($aMatch[1])
                ];
            }
        }

        return $flashcards;
    }

    /**
     * Handle chat request with task-specific logic
     */
    public function handleChatRequest($conn, $studentId, $userMessage, $task = "chat", $moduleId = null)
    {
        // Get student info
        $stmt = $conn->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
        $stmt->execute([$studentId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $studentName = $user ? $user['firstname'] : "";

        // Get progress data
        $progressData = $this->getStudentProgress($conn, $studentId);

        // Special handling for flashcard task
        if ($task === "flashcard") {
            $lessonContent = null;
            
            // If module ID provided, get its content
            if ($moduleId) {
                $lessonContent = $this->getModuleContent($conn, $moduleId);
            }

            // Build prompts with lesson content
            $systemPrompt = $this->buildSystemPrompt($task, $studentName);
            $userPrompt = $this->buildUserPrompt($task, $userMessage, $progressData, $lessonContent);
            
            // Send to OpenAI
            $response = $this->sendMessage($userPrompt, $systemPrompt, 1000);

            if (isset($response['error'])) {
                return ['error' => $response['error']];
            }

            $content = $response['content'];
            $flashcards = $this->parseFlashcards($content);
            
            return [
                'reply' => $content,
                'flashcards' => $flashcards,
                'task' => 'flashcard',
                'module_id' => $moduleId
            ];
        }

        // For progress or chat tasks
        $systemPrompt = $this->buildSystemPrompt($task, $studentName);
        $userPrompt = $this->buildUserPrompt($task, $userMessage, $progressData);
        
        // Set token limit
        $maxTokens = 500;

        // Send to OpenAI
        $response = $this->sendMessage($userPrompt, $systemPrompt, $maxTokens);

        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }

        return [
            'reply' => $response['content'],
            'task' => $task
        ];
    }

    /**
     * Get student modules list for selection
     */
    public function getModulesForStudent($conn, $studentId)
    {
        return $this->getStudentModules($conn, $studentId);
    }
}