# StudyWS - Nginx Setup Guide

## Overview
This configuration sets up Nginx as a reverse proxy for the StudyWS platform, routing traffic to:
- **PHP Backend REST API** (port 8080) - handles authentication, CRUD operations
- **WebSocket Gateway** (port 8090) - handles real-time job status updates
- **Python AI Workers** (port 8000) - handles transcription, summarization, flashcard generation...

## Architecture Flow
```
Client (Flutter/Web)
    ↓ HTTP/HTTPS
Nginx (port 80/443) - Reverse Proxy
    ├→ /api/*         → PHP Backend (port 8080)
    ├→ /ws/*          → WebSocket Gateway (port 8090)
    └→ /ai/* (internal) → Python Workers (port 8000)
```

## Installation

### Windows
1. Download Nginx from: https://nginx.org/en/download.html
2. Extract to `C:\nginx` or your preferred location
3. Copy `nginx.conf` to `C:\nginx\conf\nginx.conf`

### macOS (Homebrew)
```bash
brew install nginx
cp nginx.conf /usr/local/etc/nginx/nginx.conf
```

### Linux (Ubuntu/Debian)
```bash
sudo apt update
sudo apt install nginx
sudo cp nginx.conf /etc/nginx/nginx.conf
```

## Configuration

### 1. Update Backend Ports (if different)
Edit `nginx.conf` and update the upstream servers:

```nginx
upstream php_backend {
    server localhost:8080;  # Change if PHP runs on different port
}

upstream websocket_gateway {
    server localhost:8090;  # Change if WebSocket runs on different port
}

upstream python_workers {
    server localhost:8000;  # Change if Python workers run on different port
}
```

### 2. Adjust File Upload Size (if needed)
Default is 100MB for audio/video uploads:
```nginx
client_max_body_size 100M;  # Increase if needed
```

### 3. Rate Limiting (optional)
Current limits:
- API endpoints: 10 requests/second per IP
- Auth endpoints: 5 requests/minute per IP

Adjust in `nginx.conf`:
```nginx
limit_req_zone $binary_remote_addr zone=api_limit:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=auth_limit:10m rate=5r/m;
```

## Starting Nginx

### Windows
```cmd
cd C:\nginx
start nginx
```

### macOS/Linux
```bash
sudo nginx
# or with systemctl
sudo systemctl start nginx
```

## Managing Nginx

### Test Configuration
```bash
# Windows
nginx -t

# macOS/Linux
sudo nginx -t
```

### Reload Configuration (without downtime)
```bash
# Windows
nginx -s reload

# macOS/Linux
sudo nginx -s reload
```

### Stop Nginx
```bash
# Windows
nginx -s stop

# macOS/Linux
sudo systemctl stop nginx
# or
sudo nginx -s stop
```

### View Logs
```bash
# Windows
type C:\nginx\logs\access.log
type C:\nginx\logs\error.log

# macOS
tail -f /usr/local/var/log/nginx/access.log

# Linux
tail -f /var/log/nginx/access.log
```

## Testing the Setup

### 1. Check Nginx is Running
```bash
curl http://localhost/health
# Expected: "healthy"
```

### 2. Test API Endpoint (once PHP backend is running)
```bash
curl http://localhost/api/health
# Should proxy to PHP backend
```

### 3. Test WebSocket Connection (once WS gateway is running)
```javascript
const ws = new WebSocket('ws://localhost/ws/jobs/123');
ws.onmessage = (event) => console.log(event.data);
```

## Endpoints Reference

| Path | Destination | Description |
|------|-------------|-------------|
| `/` | Nginx | Static welcome message |
| `/health` | Nginx | Health check endpoint |
| `/api/auth/*` | PHP Backend | Authentication (stricter rate limit) |
| `/api/*` | PHP Backend | REST API endpoints |
| `/ws/*` | WebSocket Gateway | Real-time connections |
| `/ai/*` | Python Workers | AI microservices (internal only) |
| `/storage/*` | Not configured | Static files/MinIO (needs setup) |

## Production Considerations

### 1. Enable HTTPS
Uncomment and configure the HTTPS server block in `nginx.conf`:
```nginx
server {
    listen 443 ssl http2;
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    # ... rest of configuration
}
```

### 2. Use Real Domain Name
Replace `localhost studyws.local` with your actual domain:
```nginx
server_name api.studyws.com;
```

### 3. Tighten Security
- Remove `Access-Control-Allow-Origin *` and specify allowed origins
- Implement authentication validation for `/storage/` endpoint
- Add SSL/TLS configuration
- Configure firewall rules

### 4. Enable Caching
Add caching for static assets:
```nginx
location /static/ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

### 5. Load Balancing
Add multiple backend servers:
```nginx
upstream php_backend {
    server localhost:8080;
    server localhost:8081;
    server localhost:8082;
}
```

## Troubleshooting

### Issue: Connection Refused
- Ensure backend services are running on configured ports
- Check firewall settings
- Verify port numbers in upstream configuration

### Issue: 502 Bad Gateway
- Backend service is down or not responding
- Check backend logs
- Verify network connectivity to backend

### Issue: 413 Request Entity Too Large
- Increase `client_max_body_size` in nginx.conf
- Reload Nginx after changes

### Issue: WebSocket Connection Fails
- Verify `Upgrade` and `Connection` headers are properly set
- Check WebSocket gateway is running
- Ensure no other proxy is interfering

## Resources
- [Nginx Documentation](https://nginx.org/en/docs/)
- [Nginx Reverse Proxy Guide](https://docs.nginx.com/nginx/admin-guide/web-server/reverse-proxy/)
- [WebSocket Proxying](https://nginx.org/en/docs/http/websocket.html)
