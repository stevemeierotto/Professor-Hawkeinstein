FROM ubuntu:22.04

# Prevent timezone prompts during install
ENV DEBIAN_FRONTEND=noninteractive

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    cmake \
    g++ \
    libcurl4-openssl-dev \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Clone and build llama.cpp
WORKDIR /build
RUN git clone https://github.com/ggerganov/llama.cpp.git
WORKDIR /build/llama.cpp
RUN cmake -B build && cmake --build build --config Release -j$(nproc)

# Create app directory and copy binary
WORKDIR /app
RUN cp /build/llama.cpp/build/bin/llama-server /app/ || cp /build/llama.cpp/build/llama-server /app/ || find /build -name "llama-server" -type f -exec cp {} /app/ \;

# Models will be mounted as volume
VOLUME /app/models

# Expose llama-server port
EXPOSE 8090

# Default command - optimized for fast responses (4-9 seconds for chat)
# Settings based on performance tuning: 90% faster than original configuration
CMD ["./llama-server", "--host", "0.0.0.0", "--port", "8090", \
     "--model", "/app/models/qwen2.5-1.5b-instruct-q4_k_m.gguf", \
     "--threads", "4", "--ctx-size", "4096", "--n-predict", "512", \
     "--threads-batch", "4", "--cache-reuse", "256", \
     "--parallel", "2", "--cont-batching"]
