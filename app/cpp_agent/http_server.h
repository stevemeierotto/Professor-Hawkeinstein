#pragma once
#include <string>
#include "agent_manager.h"
#include "database.h"

class HttpServer {
public:
    HttpServer(int port, AgentManager& agentManager, Database& db);
    void run();
private:
    int port_;
    AgentManager& agentManager_;
    Database& db_;
};
