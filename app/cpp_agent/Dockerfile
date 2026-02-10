FROM ubuntu:22.04

# Prevent timezone prompts during install
ENV DEBIAN_FRONTEND=noninteractive

# Install dependencies for building and running
RUN apt-get update && apt-get install -y \
    g++ \
    make \
    libcurl4-openssl-dev \
    libjsoncpp-dev \
    libmysqlclient-dev \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Copy source code
COPY src/ ./src/
COPY include/ ./include/
COPY Makefile ./
COPY config.docker.json ./config.json

# Build the agent service
RUN make clean || true && make

# Expose port for the agent service
EXPOSE 8080

# Run the agent service
CMD ["./bin/agent_service"]
