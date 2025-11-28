# Agent Factory System - Quick Start

## What Was Built

A complete **Agent Factory** system that enables:

1. **Web Scraping**: Scrape educational content from URLs with metadata tracking
2. **Content Review**: Review scraped content for accuracy with quality scoring
3. **Agent Creation**: Build specialized grade-level agents using a wizard interface
4. **Fine-Tuning Pipeline**: Export conversation data for model fine-tuning with Ollama
5. **Metadata Tracking**: Complete audit trail of content sources and accuracy verification

## Key Features

### ✅ Implemented Components

- **Admin Dashboard** (`admin_dashboard.html`) - Central control panel
- **Content Scraper** (`admin_scraper.html`) - Web scraping interface
- **Content Review** (`admin_content_review.html`) - Quality assurance workflow
- **Agent Factory** (`admin_agent_factory.html`) - 4-step wizard for agent creation
- **Fine-Tuning Export** (`admin_finetuning.html`) - Training data export interface
- **Database Schema** - 4 new tables for scraped content, reviews, exports, and activity logs
- **Admin APIs** - 10+ backend endpoints for all operations
- **Authorization** - Role-based access control (admin only)

### 🎯 Your Use Case

**Goal**: Create Math Grade 1 and Math Grade 2 agents

**Workflow**:
1. Scrape Common Core math standards for Grade 1 and 2
2. Review content for accuracy, note source metadata
3. Use Agent Factory to create specialized agents:
   - **Ms. Numbers (Grade 1)**: Simple language, visual examples, counting strategies
   - **Professor Numbers (Grade 2)**: Bridge to abstract thinking, mental math
4. Students interact with agents
5. Export high-quality conversations as JSONL
6. Fine-tune Ollama models with exported data

## Getting Started (3 Steps)

### Step 1: Database Setup

```bash
cd /home/steve/Professor_Hawkeinstein
./setup_agent_factory.sh
```

This creates:
- `admin_activity_log` table
- `scraped_content` table
- `content_reviews` table  
- `training_exports` table
- `media/training_exports/` directory

### Step 2: Access Admin Dashboard

1. Navigate to: `http://your-server/admin_dashboard.html`
2. Login: 
   - Username: `admin`
   - Password: `admin123`
3. Explore the 9 admin modules

### Step 3: Create Your First Agent

**Example: Math Grade 1 Agent**

1. **Scrape Content**:
   - Go to Content Scraper
   - URL: `http://www.corestandards.org/Math/Content/1/`
   - Grade: "Grade 1", Subject: "Mathematics"
   - Click "Scrape Content"

2. **Review Content**:
   - Go to Content Review
   - Review scraped Common Core standards
   - Score: Accuracy (0.95), Relevance (1.0), Quality (0.95)
   - Recommendation: "Approve"
   - Submit review

3. **Create Agent**:
   - Go to Agent Factory
   - Select "Elementary Math Tutor" template
   - Configure:
     - Name: "Ms. Numbers"
     - Grade: "Grade 1"
     - Subject: "Mathematics"
     - Model: "llama2"
   - Add approved content to knowledge base
   - Create agent

4. **Test Agent**:
   - Students use workbook interface
   - Agent references scraped standards
   - Conversations stored with quality scores

5. **Export & Fine-Tune** (After collecting data):
   - Go to Model Fine-Tuning
   - Select "Ms. Numbers" agent
   - Export as JSONL
   - Use Ollama to fine-tune model
   - Update agent to use fine-tuned model

## File Structure

```
/home/steve/Professor_Hawkeinstein/
├── admin_dashboard.html           # Main admin hub
├── admin_scraper.html             # Content scraping
├── admin_content_review.html      # Review workflow
├── admin_agent_factory.html       # Agent creation wizard
├── admin_finetuning.html          # Training export
├── schema.sql                     # Database (updated)
├── setup_agent_factory.sh         # Setup script
├── AGENT_FACTORY_GUIDE.md         # Detailed documentation
└── api/admin/
    ├── auth_check.php             # Authorization
    ├── scraper.php                # Web scraping
    ├── review_content.php         # Content review
    ├── create_agent.php           # Agent creation
    ├── export_training_data.php   # Training export
    └── [6 more APIs]              # Supporting endpoints
```

## Creating Math Grade 1 Agent

### Recommended Content Sources

```
Grade 1 Math Standards:
- http://www.corestandards.org/Math/Content/1/
- Khan Academy Grade 1 lessons
- NCTM early elementary resources

Key Topics:
- Counting to 120
- Addition/subtraction within 20
- Place value (tens/ones)
- Measuring length
- Telling time (hour/half hour)
- 2D and 3D shapes
```

### Agent Configuration

```json
{
  "name": "Ms. Numbers",
  "type": "math_tutor",
  "grade_level": "grade_1",
  "subject_area": "mathematics",
  "model": "llama2",
  "temperature": 0.6,
  "system_prompt": "You are Ms. Numbers, a warm and patient first grade math tutor. You use very simple language, visual examples with counting objects, and celebrate every correct answer. You help children build confidence with numbers through encouraging, step-by-step guidance."
}
```

## Creating Math Grade 2 Agent

### Recommended Content Sources

```
Grade 2 Math Standards:
- http://www.corestandards.org/Math/Content/2/
- Khan Academy Grade 2 lessons
- Grade 2 word problem resources

Key Topics:
- Addition/subtraction within 100 (with regrouping)
- Place value to 1000
- Money (coins and bills)
- Time to 5-minute intervals
- Measurement (standard units)
- Arrays and repeated addition
- Word problems (multi-step)
```

### Agent Configuration

```json
{
  "name": "Professor Numbers",
  "type": "math_tutor",
  "grade_level": "grade_2",
  "subject_area": "mathematics",
  "model": "llama2",
  "temperature": 0.7,
  "system_prompt": "You are Professor Numbers, an enthusiastic second grade math tutor. You bridge concrete examples to beginning abstract thinking, introduce mental math strategies, and connect math to real-world situations like money and time. You celebrate logical thinking and encourage multiple solution strategies."
}
```

## Fine-Tuning Process

### 1. Collect Training Data
- Students interact with agents
- High-quality conversations (importance > 0.7) are saved
- Collect 100-1000+ interactions per agent

### 2. Export Data
```
Admin Dashboard → Model Fine-Tuning → Export Training Data
- Agent: Select your agent
- Format: JSONL
- Min Importance: 0.70
- Export
```

### 3. Create Ollama Modelfile
```
FROM llama2
ADAPTER /path/to/training_export.jsonl
SYSTEM """[Your agent's system prompt]"""
PARAMETER temperature 0.7
```

### 4. Fine-Tune
```bash
ollama create ms-math-grade1 -f Modelfile
```

### 5. Update Agent
```sql
UPDATE agents 
SET model_name = 'ms-math-grade1' 
WHERE agent_name = 'Ms. Numbers';
```

## Metadata Tracking

Every piece of scraped content includes:
- **Source URL**: Where content came from
- **Credibility Score**: 0.00-1.00 (auto-calculated based on domain)
- **Review Status**: pending → approved/rejected
- **Quality Scores**: Accuracy, relevance, quality (reviewer assigned)
- **Fact-Check Notes**: Specific verification details
- **Grade/Subject**: Classification metadata

This ensures:
✅ Full audit trail of content sources  
✅ Quality assurance before use  
✅ Ability to track which content influences which agent responses  
✅ Research-grade metadata for academic use

## API Authentication

All admin endpoints require:
```javascript
fetch('api/admin/endpoint.php', {
    headers: {
        'Authorization': 'Bearer ' + localStorage.getItem('token')
    }
})
```

Token is set during login with admin credentials.

## Troubleshooting

**Can't access admin pages?**
- Verify admin user exists: `SELECT * FROM users WHERE role='admin'`
- Check localStorage has token: Open browser console, type `localStorage.getItem('token')`

**Scraper returns empty content?**
- Some sites block automated scrapers
- JavaScript-heavy sites need headless browser (not implemented)
- Try .edu/.gov educational sites first

**No conversations to export?**
- Agent needs to have student interactions first
- Check `agent_memories` table: `SELECT COUNT(*) FROM agent_memories WHERE agent_id=YOUR_ID`

**Training export fails?**
- Ensure `media/training_exports/` exists with write permissions
- Check disk space

## Next Steps

1. **Run setup script**: `./setup_agent_factory.sh`
2. **Login to admin dashboard**: http://your-server/admin_dashboard.html
3. **Scrape Grade 1 math content**: Use Content Scraper
4. **Review and approve**: Use Content Review interface
5. **Create Math Grade 1 agent**: Use Agent Factory wizard
6. **Repeat for Grade 2**: Same process with Grade 2 content
7. **Test agents**: Have students interact via workbook interface
8. **Export training data**: When enough quality data collected
9. **Fine-tune models**: Use Ollama with exported JSONL
10. **Deploy fine-tuned agents**: Update agent model names in database

## Documentation

- **Full Guide**: `AGENT_FACTORY_GUIDE.md` (comprehensive technical documentation)
- **This File**: `AGENT_FACTORY_QUICKSTART.md` (quick reference)
- **Database Schema**: `schema.sql` (database structure)

## Support

Questions? Check:
1. `AGENT_FACTORY_GUIDE.md` - Detailed implementation guide
2. Code comments in PHP files
3. Browser console for JavaScript errors
4. PHP error logs for backend issues

---

**You're ready to create specialized educational agents! 🚀**

The system is designed to scale:
- Create agents for different grades
- Create agents for different subjects
- Export and fine-tune models
- Track everything with metadata

Start with Math Grade 1 and 2, then expand to other subjects and grade levels as needed.
