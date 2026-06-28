# WordPress Deployment Guide

This directory contains the deployment configurations, scripts, credentials, and reference commands for building, pushing, and deploying the WordPress project.

## 🔑 Credentials & Host Information

### Production SSH Server
- **Host:** `163.61.73.174`
- **Username:** `root`
- **Password:** `HKD_Registry_2026_Secure!`

### Private Docker Registry
- **URL:** `https://registry.bkit.vn`
- **Image:** `registry.bkit.vn/library/wordpress:latest`
- **Username:** `admin`
- **Password:** `HKD_Registry_2026_Secure!`

---

## 🚀 Automated Deployment

You can run the provided Python deployment script to automate the entire pipeline (local login, build, push, remote pull, and container restart).

### Prerequisites
- Python 3.x
- Docker installed and running locally
- Python `paramiko` library installed:
  ```bash
  pip install paramiko
  ```

### Usage
Run the deployment script from this directory or the root directory:
```bash
python deploy/deploy-wordpress.py
```

---

## 🛠️ Manual Deployment Commands

If you prefer to perform the deployment steps manually, follow these instructions:

### Step 1: Log in and build the image locally
Open terminal in the project root directory and run:
```bash
# Log in to private registry
docker login registry.bkit.vn -u admin -p HKD_Registry_2026_Secure!

# Build the local Docker image
docker build -t registry.bkit.vn/library/wordpress:latest .

# Push the image to the private registry
docker push registry.bkit.vn/library/wordpress:latest
```

### Step 2: SSH into the production server
```bash
ssh root@163.61.73.174
# Enter password: HKD_Registry_2026_Secure!
```

### Step 3: Pull and restart the container on the production server
Run the following commands inside the SSH session:
```bash
# Log in to registry on server
docker login registry.bkit.vn -u admin -p HKD_Registry_2026_Secure!

# Go to docker-compose folder
cd /var/www/wordpress-ketoan

# Pull the new image
docker compose pull

# Restart the service
docker compose up -d
```

---

## ⚙️ Container Configuration Reference

On the production server, the container is defined as follows in `/var/www/wordpress-ketoan/docker-compose.yml`:

```yaml
services:
  web:
    image: registry.bkit.vn/library/wordpress:latest
    container_name: wordpress-ketoan
    entrypoint: ["sh", "-c", "mkdir -p /var/www/html/wp-content/themes/bkit && cp -a /usr/src/wordpress/wp-content/themes/bkit/. /var/www/html/wp-content/themes/bkit/ && chown -R www-data:www-data /var/www/html/wp-content/themes/bkit && exec docker-entrypoint.sh apache2-foreground"]
    restart: always
    ports:
      - "8085:80"
    environment:
      WORDPRESS_DB_HOST: host.docker.internal
      WORDPRESS_DB_USER: root
      WORDPRESS_DB_PASSWORD: HKD_Registry_2026_Secure!
      WORDPRESS_DB_NAME: wordpress
    extra_hosts:
      - "host.docker.internal:host-gateway"
    volumes:
      - wordpress_data:/var/www/html

volumes:
  wordpress_data:
```
