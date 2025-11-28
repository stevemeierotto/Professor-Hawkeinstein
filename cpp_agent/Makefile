# Makefile for Professor Hawkeinstein's Agent Service
CXX = g++
CXXFLAGS = -std=c++17 -Wall -Wextra -O2 -Iinclude
LDFLAGS = -lcurl -ljsoncpp -lmysqlclient -lpthread

# Directories
SRC_DIR = src
INC_DIR = include
BUILD_DIR = build
BIN_DIR = bin

# Source files
SOURCES = $(filter-out $(SRC_DIR)/simple_server.cpp, $(wildcard $(SRC_DIR)/*.cpp))
OBJECTS = $(SOURCES:$(SRC_DIR)/%.cpp=$(BUILD_DIR)/%.o)
TARGET = $(BIN_DIR)/agent_service

# Default target
all: directories $(TARGET)

# Create necessary directories
directories:
	@mkdir -p $(BUILD_DIR)
	@mkdir -p $(BIN_DIR)

# Link
$(TARGET): $(OBJECTS)
	$(CXX) $(OBJECTS) -o $@ $(LDFLAGS)
	@echo "Build complete: $(TARGET)"

# Compile
$(BUILD_DIR)/%.o: $(SRC_DIR)/%.cpp
	$(CXX) $(CXXFLAGS) -c $< -o $@

# Clean
clean:
	rm -rf $(BUILD_DIR) $(BIN_DIR)

# Install
install: $(TARGET)
	sudo cp $(TARGET) /usr/local/bin/
	sudo mkdir -p /etc/professorhawkeinstein
	sudo cp config.json /etc/professorhawkeinstein/
	@echo "Installation complete"

# Run
run: $(TARGET)
	./$(TARGET)

# Check dependencies
check-deps:
	@echo "Checking dependencies..."
	@pkg-config --exists libcurl && echo "✓ libcurl found" || echo "✗ libcurl NOT found - install libcurl4-openssl-dev"
	@pkg-config --exists jsoncpp && echo "✓ jsoncpp found" || echo "✗ jsoncpp NOT found - install libjsoncpp-dev"
	@ldconfig -p | grep -q libmysqlclient && echo "✓ mysqlclient found" || echo "✗ mysqlclient NOT found - install libmariadb-dev"

.PHONY: all directories clean install run check-deps
