// http_server.cpp
#include "http_server.h"
#include "agent_manager.h"
#include "database.h"
#include "llamacpp_client.h"
#include "external/httplib/httplib.h"
#include "external/json/json.hpp"
#include <iostream>

using json = nlohmann::json;
using namespace httplib;

HttpServer::HttpServer(int port, AgentManager& agentManager, Database& db)
	: port_(port), agentManager_(agentManager), db_(db) {}

void HttpServer::run() {
	Server svr;

	// Add CORS headers for all responses
	svr.set_default_headers({
		{"Access-Control-Allow-Origin", "*"},
		{"Access-Control-Allow-Methods", "GET, POST, OPTIONS"},
		{"Access-Control-Allow-Headers", "Content-Type, Authorization"}
	});

	// Handle OPTIONS requests for CORS preflight
	svr.Options(".*", [](const Request&, Response& res) {
		res.status = 204;
	});

	// Health check
	svr.Get("/health", [](const Request&, Response& res) {
		res.set_content("{\"status\":\"ok\"}", "application/json");
	});

	// DEPRECATED FILE - DO NOT USE
	// POST /api/chat - REMOVED - Use /agent/chat in src/http_server.cpp instead
	svr.Post("/api/chat", [](const Request& req, Response& res) {
		res.status = 410;
		json errorJson = {
			{"error", "Endpoint removed"},
			{"message", "/api/chat is deprecated. Use /agent/chat instead."},
			{"status", 410}
		};
		res.set_content(errorJson.dump(), "application/json");
	});

	// GET /advisor?student_id=#
	svr.Get("/advisor", [this](const Request& req, Response& res) {
		int studentId = std::stoi(req.get_param_value("student_id"));
		// For demo, agentId = 0 (Professor Hawkeinstein)
		StudentAdvisor advisor = db_.getStudentAdvisor(studentId, 0);
		   json j = {
			   {"customSystemPrompt", advisor.customSystemPrompt},
			   {"conversationHistory", json::array()}
		   };
		   for (const auto& entry : advisor.conversationHistory) {
			   j["conversationHistory"].push_back({
				   {"role", entry.role},
				   {"text", entry.text},
				   {"timestamp", entry.timestamp}
			   });
		   }
		res.set_content(j.dump(), "application/json");
	});

	// POST /agent/message
	svr.Post("/agent/message", [this](const Request& req, Response& res) {
		auto j = json::parse(req.body);
		int userId = j["userId"].get<int>();
		int agentId = j["agentId"].get<int>();
		std::string message = j["message"].get<std::string>();
		std::string result = agentManager_.processMessage(userId, agentId, message);
		res.set_content(result, "application/json");
	});

	// GET /student/<id>
	svr.Get(R"(/student/(\d+))", [this](const Request& req, Response& res) {
		int studentId = std::stoi(req.matches[1]);
		// For demo, agentId = 0
		StudentAdvisor advisor = db_.getStudentAdvisor(studentId, 0);
		   json j = {
			   {"customSystemPrompt", advisor.customSystemPrompt},
			   {"conversationHistory", json::array()}
		   };
		   for (const auto& entry : advisor.conversationHistory) {
			   j["conversationHistory"].push_back({
				   {"role", entry.role},
				   {"text", entry.text},
				   {"timestamp", entry.timestamp}
			   });
		   }
		res.set_content(j.dump(), "application/json");
	});

	// POST /placement_test
	svr.Post("/placement_test", [this](const Request& req, Response& res) {
		// Placeholder: implement placement test logic
		res.set_content("{\"status\":\"not implemented\"}", "application/json");
	});

	// POST /load_agent
	svr.Post("/load_agent", [this](const Request& req, Response& res) {
		auto j = json::parse(req.body);
		int agentId = j["agentId"].get<int>();
		AgentConfig config = db_.getAgentConfig(agentId);
		json out = {
			{"modelPath", config.modelPath},
			{"contextLength", config.contextLength},
			{"temperature", config.temperature}
		};
		res.set_content(out.dump(), "application/json");
	});

	std::cout << "HTTP server running on port " << port_ << std::endl;
	svr.listen("0.0.0.0", port_);
}


