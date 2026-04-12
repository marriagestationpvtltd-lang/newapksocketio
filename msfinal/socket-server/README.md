# Socket.IO Chat Server

Real-time chat server for the Marriage Station Flutter app, replacing Firebase Firestore with Socket.IO + MySQL.

## Requirements
- Node.js >= 18
- MySQL >= 5.7 (the `ms` database must already exist)

## Setup

### 1. Environment

An `.env.example` template is included in this folder:
```bash
cp .env.example .env
# Edit .env with your MySQL credentials
```

Minimum `.env` for local development:
```
PORT=3001
DB_HOST=localhost
DB_NAME=ms
DB_USER=root
DB_PASS=yourpassword
```

### 2. Install & start
```bash
npm install
npm start        # production
npm run dev      # development (auto-reload)
```

> **The server auto-creates all required chat tables on first start.**
> You do NOT need to run `sql/chat_tables.sql` manually. The SQL file is kept
> only as a reference.

### 3. Flutter app — point to the correct server URL

Edit `msfinal/lib/config/app_endpoints.dart` and change the default IP to your
machine's LAN IP address. If you only override `API_BASE_URL` for a custom
environment, the Flutter app will now automatically fall back to the same host
on port **3001** for the socket server unless `SOCKET_SERVER_URL` is set.

```dart
const String kSocketServerBaseUrl = String.fromEnvironment(
  'SOCKET_SERVER_URL',
  defaultValue: 'http://192.168.X.X:3001',  // ← change to your LAN IP
);
```

Or pass it at build time without editing the file:
```bash
# Android / iOS
flutter run --dart-define=SOCKET_SERVER_URL=http://192.168.X.X:3001

# Web
flutter run -d chrome --dart-define=SOCKET_SERVER_URL=http://localhost:3001
```

**Finding your LAN IP:**
- Linux/macOS: `ip addr` or `ifconfig` (look for `inet` under `wlan0` / `en0`)
- Windows: `ipconfig` (look for IPv4 Address under Wi-Fi)

> ⚠️ Both your development machine (running the server) and the mobile device
> must be on the **same Wi-Fi network**. Connecting via mobile data will not
> reach a local server.


### Client → Server
| Event | Payload |
|---|---|
| `authenticate` | `{userId}` |
| `join_room` | `{chatRoomId}` |
| `leave_room` | `{chatRoomId}` |
| `send_message` | `{chatRoomId, senderId, receiverId, message, messageType, messageId?, repliedTo?, isReceiverViewing?}` |
| `typing_start` | `{chatRoomId, userId}` |
| `typing_stop` | `{chatRoomId, userId}` |
| `mark_read` | `{chatRoomId, userId}` |
| `set_active_chat` | `{userId, chatRoomId, isActive}` |
| `get_messages` | `{chatRoomId, page, limit}` + ack callback |
| `get_chat_rooms` | `{userId}` + ack callback |
| `edit_message` | `{chatRoomId, messageId, newMessage}` |
| `delete_message` | `{chatRoomId, messageId, userId, deleteForEveryone}` |

### Server → Client
| Event | Payload |
|---|---|
| `authenticated` | `{success, userId}` |
| `new_message` | message object |
| `message_edited` | `{chatRoomId, messageId, newMessage, editedAt}` |
| `message_deleted` | `{chatRoomId, messageId, deleteForEveryone, userId}` |
| `typing_start` | `{chatRoomId, userId}` |
| `typing_stop` | `{chatRoomId, userId}` |
| `messages_read` | `{chatRoomId, userId}` |
| `user_status_change` | `{userId, isOnline, lastSeen}` |
| `chat_rooms_update` | `{chatRooms: [...]}` |
| `error` | `{message}` |

## REST Endpoints
| Method | Path | Description |
|---|---|---|
| `POST` | `/upload?type=image\|voice` | Upload chat media. Returns `{url}` |
| `GET` | `/health` | Health check |

## Flutter Integration
Set `kSocketServerUrl` in `lib/service/socket_service.dart` to the server's URL.
