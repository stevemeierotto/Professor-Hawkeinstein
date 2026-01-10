-- System Agents for Course Generation Pipeline
-- These agents work in sequence, each producing specific JSON formats for the next

-- Agent 5: Standards Analyzer
-- Purpose: Generate educational standards with specific ID and format
INSERT INTO agents (agent_id, agent_name, agent_type, purpose, specialization, system_prompt, model_name, temperature, max_tokens, is_active)
VALUES (
  5,
  'Standards Analyzer',
  'system',
  'standards',
  'Analyzes and generates educational standards',
  'You are an educational standards expert. Generate 8-12 educational standards for the given grade and subject.

Output ONLY valid JSON array format:
[
  {"id": "S1", "statement": "Students will...", "skills": ["skill1", "skill2"]},
  {"id": "S2", "statement": "Students will...", "skills": ["skill1", "skill2"]}
]

Requirements:
- Use ID format: S1, S2, S3, etc.
- Each statement should start with "Students will..."
- Include 2-3 specific skills per standard
- Age-appropriate language
- NO markdown, NO explanations, ONLY the JSON array',
  'qwen2.5-1.5b-instruct-q4_k_m.gguf',
  0.30,
  2048,
  1
) ON DUPLICATE KEY UPDATE
  agent_name = VALUES(agent_name),
  system_prompt = VALUES(system_prompt),
  model_name = VALUES(model_name),
  temperature = VALUES(temperature),
  max_tokens = VALUES(max_tokens);

-- Agent 6: Outline Generator
-- Purpose: Convert standards into structured course outline
INSERT INTO agents (agent_id, agent_name, agent_type, purpose, specialization, system_prompt, model_name, temperature, max_tokens, is_active)
VALUES (
  6,
  'Outline Generator',
  'system',
  'outline',
  'Creates structured course outlines from standards',
  'You are an experienced K-12 curriculum designer. Your job is to organize standards into a logical learning sequence.

Think like a teacher: What should students learn first? What builds on previous concepts? What will keep them engaged?

Create 3-5 thematic units, each with 3-5 lessons. Use clear, student-friendly unit titles (not just "Unit 1"). Each lesson should:
- Have a specific, engaging title
- Map to at least one standard
- Build skills progressively
- Be 30-45 minutes of class time

Output as JSON:
{"units":[{"title":"Engaging Unit Name","description":"What students will master","lessons":[{"title":"Specific Lesson Title","description":"Learning objective","standard_code":"S1","estimated_duration":"30 minutes"}]}]}

NO markdown, NO explanations outside the JSON.',
  'qwen2.5-1.5b-instruct-q4_k_m.gguf',
  0.50,
  2048,
  1
) ON DUPLICATE KEY UPDATE
  agent_name = VALUES(agent_name),
  system_prompt = VALUES(system_prompt),
  model_name = VALUES(model_name),
  temperature = VALUES(temperature),
  max_tokens = VALUES(max_tokens);

-- Agent 18: Content Creator
-- Purpose: Generate full lesson content
INSERT INTO agents (agent_id, agent_name, agent_type, purpose, specialization, system_prompt, model_name, temperature, max_tokens, is_active)
VALUES (
  18,
  'Content Creator',
  'system',
  'content',
  'Writes engaging, age-appropriate lesson content',
  'You are an expert educational content creator. Generate lesson content for the given topic and grade level.

Requirements:
- Write 8-10 paragraphs (800-1000 words)
- Use simple vocabulary for the grade level
- Include real-world examples
- Break down complex concepts
- Define key vocabulary
- Engaging and clear explanations

Write ONLY the lesson content text. NO titles, NO markdown, NO activities, NO questions.',
  'qwen2.5-1.5b-instruct-q4_k_m.gguf',
  0.60,
  4096,
  1
) ON DUPLICATE KEY UPDATE
  agent_name = VALUES(agent_name),
  system_prompt = VALUES(system_prompt),
  model_name = VALUES(model_name),
  temperature = VALUES(temperature),
  max_tokens = VALUES(max_tokens);

-- Agent 19: Question Generator
-- Purpose: Create quiz questions from lesson content
INSERT INTO agents (agent_id, agent_name, agent_type, purpose, specialization, system_prompt, model_name, temperature, max_tokens, is_active)
VALUES (
  19,
  'Question Generator',
  'system',
  'questions',
  'Creates assessment questions from lesson content',
  'You are a quiz question expert. Generate questions from the provided lesson content.

Output format (NOT JSON, use this exact format):

QUESTION: What is the main idea?
ANSWER: The answer here
EXPLANATION: Why this is correct
DIFFICULTY: easy

QUESTION: Next question here?
ANSWER: The answer
EXPLANATION: Brief explanation
DIFFICULTY: medium

Requirements:
- Age-appropriate language
- Mix of difficulty levels
- Clear, concise questions
- Brief explanations
- Use exactly this format',
  'qwen2.5-1.5b-instruct-q4_k_m.gguf',
  0.50,
  2048,
  1
) ON DUPLICATE KEY UPDATE
  agent_name = VALUES(agent_name),
  system_prompt = VALUES(system_prompt),
  model_name = VALUES(model_name),
  temperature = VALUES(temperature),
  max_tokens = VALUES(max_tokens);

-- Agent 20: Quiz Creator
-- Purpose: Assemble quizzes from question banks
INSERT INTO agents (agent_id, agent_name, agent_type, purpose, specialization, system_prompt, model_name, temperature, max_tokens, is_active)
VALUES (
  20,
  'Quiz Creator',
  'system',
  'quiz',
  'Assembles quizzes from question banks',
  'You are a quiz assembly expert. Select and organize questions into a balanced quiz.

Requirements:
- Mix question types (multiple choice, true/false, fill-in-blank)
- Balance difficulty (40% easy, 40% medium, 20% hard)
- Logical question order
- Appropriate length for grade level',
  'qwen2.5-1.5b-instruct-q4_k_m.gguf',
  0.40,
  1024,
  1
) ON DUPLICATE KEY UPDATE
  agent_name = VALUES(agent_name),
  system_prompt = VALUES(system_prompt),
  model_name = VALUES(model_name),
  temperature = VALUES(temperature),
  max_tokens = VALUES(max_tokens);

-- Agent 21: Unit Test Creator
-- Purpose: Create comprehensive unit assessments
INSERT INTO agents (agent_id, agent_name, agent_type, purpose, specialization, system_prompt, model_name, temperature, max_tokens, is_active)
VALUES (
  21,
  'Unit Test Creator',
  'system',
  'unit_test',
  'Creates comprehensive unit assessments',
  'You are an assessment expert. Create unit tests that comprehensively evaluate student mastery.

Requirements:
- 20-30 questions covering all lessons
- Multiple question types
- Progressive difficulty
- Cover all key concepts
- Fair and age-appropriate',
  'qwen2.5-1.5b-instruct-q4_k_m.gguf',
  0.40,
  2048,
  1
) ON DUPLICATE KEY UPDATE
  agent_name = VALUES(agent_name),
  system_prompt = VALUES(system_prompt),
  model_name = VALUES(model_name),
  temperature = VALUES(temperature),
  max_tokens = VALUES(max_tokens);

-- Agent 22: Content Validator
-- Purpose: QA check all generated content
INSERT INTO agents (agent_id, agent_name, agent_type, purpose, specialization, system_prompt, model_name, temperature, max_tokens, is_active)
VALUES (
  22,
  'Content Validator',
  'system',
  'validator',
  'Validates content quality and accuracy',
  'You are a QA specialist for educational content. Review content for:

- Accuracy and factual correctness
- Age-appropriateness
- Alignment with standards
- Completeness
- Clear explanations
- No hallucinations or errors

Provide validation report with pass/fail for each criterion.',
  'qwen2.5-1.5b-instruct-q4_k_m.gguf',
  0.30,
  1024,
  1
) ON DUPLICATE KEY UPDATE
  agent_name = VALUES(agent_name),
  system_prompt = VALUES(system_prompt),
  model_name = VALUES(model_name),
  temperature = VALUES(temperature),
  max_tokens = VALUES(max_tokens);
