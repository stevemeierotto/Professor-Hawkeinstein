/**
 * Lesson Schema Module
 * Provides structure and validation for generated lessons
 * 
 * @module LessonSchema
 */

/**
 * Create a new empty lesson template with all required fields
 * @param {number} lessonNumber - Sequential lesson number
 * @param {string} lessonTitle - Title of the lesson
 * @returns {Object} Empty lesson template
 */
export function createLessonTemplate(lessonNumber, lessonTitle) {
    return {
        lessonNumber: lessonNumber,
        lessonTitle: lessonTitle,
        objectives: [],
        explanation: "",
        guidedExamples: [],
        practiceProblems: [],
        quizQuestions: [],
        videoPlaceholder: "",
        summary: "",
        estimatedDuration: 45, // default 45 minutes
        prerequisites: [],
        tags: [],
        vocabulary: [],
        resources: [],
        difficulty: "intermediate",
        standards: [],
        metadata: {
            createdAt: new Date().toISOString(),
            updatedAt: new Date().toISOString(),
            version: "1.0.0",
            author: "Course Design Agent",
            reviewStatus: "draft"
        }
    };
}

/**
 * Add an objective to the lesson
 * @param {Object} lesson - Lesson object
 * @param {string} objective - Learning objective text
 */
export function addObjective(lesson, objective) {
    if (!lesson.objectives) lesson.objectives = [];
    lesson.objectives.push(objective);
    updateTimestamp(lesson);
}

/**
 * Add a guided example to the lesson
 * @param {Object} lesson - Lesson object
 * @param {Object} example - Example object
 * @returns {number} Example number assigned
 */
export function addGuidedExample(lesson, example) {
    if (!lesson.guidedExamples) lesson.guidedExamples = [];
    
    const exampleNumber = lesson.guidedExamples.length + 1;
    const fullExample = {
        exampleNumber: exampleNumber,
        title: example.title || `Example ${exampleNumber}`,
        problem: example.problem,
        solution: example.solution,
        steps: example.steps || [],
        explanation: example.explanation || "",
        difficulty: example.difficulty || "medium",
        ...example // Allow additional properties
    };
    
    lesson.guidedExamples.push(fullExample);
    updateTimestamp(lesson);
    return exampleNumber;
}

/**
 * Add a step to a guided example
 * @param {Object} example - Example object
 * @param {string} description - Step description
 * @param {string} work - Mathematical work or code (optional)
 */
export function addExampleStep(example, description, work = null) {
    if (!example.steps) example.steps = [];
    
    const stepNumber = example.steps.length + 1;
    const step = {
        stepNumber: stepNumber,
        description: description
    };
    
    if (work) step.work = work;
    
    example.steps.push(step);
}

/**
 * Add a practice problem to the lesson
 * @param {Object} lesson - Lesson object
 * @param {Object} problem - Problem object
 * @returns {number} Problem number assigned
 */
export function addPracticeProblem(lesson, problem) {
    if (!lesson.practiceProblems) lesson.practiceProblems = [];
    
    const problemNumber = lesson.practiceProblems.length + 1;
    const fullProblem = {
        problemNumber: problemNumber,
        question: problem.question,
        answer: problem.answer,
        hint: problem.hint || "",
        solution: problem.solution || "",
        difficulty: problem.difficulty || "medium",
        points: problem.points || 10,
        ...problem // Allow additional properties
    };
    
    lesson.practiceProblems.push(fullProblem);
    updateTimestamp(lesson);
    return problemNumber;
}

/**
 * Add a quiz question to the lesson
 * @param {Object} lesson - Lesson object
 * @param {Object} question - Question object
 * @returns {number} Question number assigned
 */
export function addQuizQuestion(lesson, question) {
    if (!lesson.quizQuestions) lesson.quizQuestions = [];
    
    const questionNumber = lesson.quizQuestions.length + 1;
    const fullQuestion = {
        questionNumber: questionNumber,
        questionText: question.questionText,
        questionType: question.questionType,
        options: question.options || [],
        correctAnswer: question.correctAnswer,
        explanation: question.explanation || "",
        points: question.points || 1,
        difficulty: question.difficulty || "medium",
        ...question // Allow additional properties
    };
    
    lesson.quizQuestions.push(fullQuestion);
    updateTimestamp(lesson);
    return questionNumber;
}

/**
 * Add vocabulary term to the lesson
 * @param {Object} lesson - Lesson object
 * @param {string} term - Vocabulary term
 * @param {string} definition - Definition of the term
 * @param {string} example - Example usage (optional)
 */
export function addVocabulary(lesson, term, definition, example = null) {
    if (!lesson.vocabulary) lesson.vocabulary = [];
    
    const vocab = {
        term: term,
        definition: definition
    };
    
    if (example) vocab.example = example;
    
    lesson.vocabulary.push(vocab);
    updateTimestamp(lesson);
}

/**
 * Add a resource to the lesson
 * @param {Object} lesson - Lesson object
 * @param {Object} resource - Resource object
 */
export function addResource(lesson, resource) {
    if (!lesson.resources) lesson.resources = [];
    
    lesson.resources.push({
        title: resource.title,
        url: resource.url,
        type: resource.type || "website",
        description: resource.description || "",
        ...resource
    });
    
    updateTimestamp(lesson);
}

/**
 * Add an educational standard to the lesson
 * @param {Object} lesson - Lesson object
 * @param {string} code - Standard code
 * @param {string} description - Standard description
 * @param {string} source - Standards source (e.g., "Common Core")
 */
export function addStandard(lesson, code, description, source = null) {
    if (!lesson.standards) lesson.standards = [];
    
    const standard = {
        code: code,
        description: description
    };
    
    if (source) standard.source = source;
    
    lesson.standards.push(standard);
    updateTimestamp(lesson);
}

/**
 * Update the lesson's timestamp
 * @param {Object} lesson - Lesson object
 */
function updateTimestamp(lesson) {
    if (!lesson.metadata) lesson.metadata = {};
    lesson.metadata.updatedAt = new Date().toISOString();
}

/**
 * Validate lesson against schema requirements
 * @param {Object} lesson - Lesson object to validate
 * @returns {Object} {valid: boolean, errors: string[]}
 */
export function validateLesson(lesson) {
    const errors = [];
    
    // Check required fields
    const required = [
        'lessonNumber', 'lessonTitle', 'objectives', 'explanation',
        'guidedExamples', 'practiceProblems', 'quizQuestions',
        'videoPlaceholder', 'summary'
    ];
    
    for (const field of required) {
        if (lesson[field] === undefined || lesson[field] === null) {
            errors.push(`Missing required field: ${field}`);
        }
    }
    
    // Validate types
    if (lesson.lessonNumber && typeof lesson.lessonNumber !== 'number') {
        errors.push('lessonNumber must be a number');
    }
    
    if (lesson.lessonTitle && typeof lesson.lessonTitle !== 'string') {
        errors.push('lessonTitle must be a string');
    }
    
    if (lesson.objectives && !Array.isArray(lesson.objectives)) {
        errors.push('objectives must be an array');
    } else if (lesson.objectives && lesson.objectives.length === 0) {
        errors.push('objectives array cannot be empty');
    }
    
    if (lesson.explanation && typeof lesson.explanation !== 'string') {
        errors.push('explanation must be a string');
    }
    
    // Validate arrays
    const arrays = ['guidedExamples', 'practiceProblems', 'quizQuestions'];
    for (const arr of arrays) {
        if (lesson[arr] && !Array.isArray(lesson[arr])) {
            errors.push(`${arr} must be an array`);
        }
    }
    
    return {
        valid: errors.length === 0,
        errors: errors
    };
}

/**
 * Get lesson statistics
 * @param {Object} lesson - Lesson object
 * @returns {Object} Statistics about the lesson
 */
export function getLessonStats(lesson) {
    return {
        objectiveCount: lesson.objectives?.length || 0,
        exampleCount: lesson.guidedExamples?.length || 0,
        practiceProblemCount: lesson.practiceProblems?.length || 0,
        quizQuestionCount: lesson.quizQuestions?.length || 0,
        vocabularyCount: lesson.vocabulary?.length || 0,
        resourceCount: lesson.resources?.length || 0,
        totalQuizPoints: lesson.quizQuestions?.reduce((sum, q) => sum + (q.points || 1), 0) || 0,
        totalPracticePoints: lesson.practiceProblems?.reduce((sum, p) => sum + (p.points || 10), 0) || 0,
        estimatedDuration: lesson.estimatedDuration || 0
    };
}

/**
 * Export lesson as JSON string
 * @param {Object} lesson - Lesson object
 * @param {boolean} pretty - Pretty print JSON
 * @returns {string} JSON string
 */
export function exportLesson(lesson, pretty = false) {
    return JSON.stringify(lesson, null, pretty ? 2 : 0);
}

/**
 * Import lesson from JSON string
 * @param {string} json - JSON string
 * @returns {Object} Lesson object
 */
export function importLesson(json) {
    try {
        return JSON.parse(json);
    } catch (error) {
        throw new Error(`Failed to parse lesson JSON: ${error.message}`);
    }
}

/**
 * Clone a lesson (deep copy)
 * @param {Object} lesson - Lesson to clone
 * @returns {Object} Cloned lesson
 */
export function cloneLesson(lesson) {
    return JSON.parse(JSON.stringify(lesson));
}

// Default export for CommonJS compatibility
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        createLessonTemplate,
        addObjective,
        addGuidedExample,
        addExampleStep,
        addPracticeProblem,
        addQuizQuestion,
        addVocabulary,
        addResource,
        addStandard,
        validateLesson,
        getLessonStats,
        exportLesson,
        importLesson,
        cloneLesson
    };
}
