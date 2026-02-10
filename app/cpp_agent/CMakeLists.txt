cmake_minimum_required(VERSION 3.10)
project(ProfessorHawkeinsteinAgentService)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

# Find required packages
find_package(CURL REQUIRED)
find_package(PkgConfig REQUIRED)
pkg_check_modules(JSONCPP REQUIRED jsoncpp)
find_library(MYSQL_LIB mysqlclient)

# Include directories
include_directories(
    ${CMAKE_SOURCE_DIR}/include
    ${CURL_INCLUDE_DIRS}
    ${JSONCPP_INCLUDE_DIRS}
)

# Source files
set(SOURCES
    src/main.cpp
    src/http_server.cpp
    src/agent_manager.cpp
    src/ollama_client.cpp
    src/database.cpp
    src/rag_engine.cpp
)

# Create executable
add_executable(agent_service ${SOURCES})

# Link libraries
target_link_libraries(agent_service
    ${CURL_LIBRARIES}
    ${JSONCPP_LIBRARIES}
    ${MYSQL_LIB}
    pthread
)

# Installation
install(TARGETS agent_service DESTINATION /usr/local/bin)
install(FILES config.json DESTINATION /etc/professorhawkeinstein)
