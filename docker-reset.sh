#!/bin/bash
set -e

echo ">>> Stopping all running containers..."
docker stop $(docker ps -aq) 2>/dev/null || true

echo ">>> Removing all containers..."
docker rm -f $(docker ps -aq) 2>/dev/null || true

echo ">>> Removing all images..."
docker rmi -f $(docker images -aq) 2>/dev/null || true

echo ">>> Removing all volumes..."
docker volume rm $(docker volume ls -q) 2>/dev/null || true

echo ">>> Removing all networks (except default ones)..."
docker network rm $(docker network ls -q) 2>/dev/null || true

echo ">>> Removing all build cache..."
docker builder prune -af

echo ">>> System prune (deep clean: containers, images, volumes, networks, cache)..."
docker system prune -af --volumes

echo ">>> Restarting Docker service..."
# Adjust based on your OS
if command -v systemctl &> /dev/null; then
  sudo systemctl restart docker
elif command -v service &> /dev/null; then
  sudo service docker restart
else
  echo "Please restart Docker manually."
fi

echo ">>> Docker reset complete. Fresh start!"
