<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once '../models/db.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class CollaborationServer implements MessageComponentInterface {
    protected $clients;
    protected $noteRooms;
    protected $userSessions;
    private $conn;
    
    public function __construct($dbConnection) {
        $this->clients = new \SplObjectStorage;
        $this->noteRooms = [];
        $this->userSessions = [];
        $this->conn = $dbConnection;
    }

    public function onOpen(ConnectionInterface $connection) {
        $this->clients->attach($connection);
        echo "New connection! ({$connection->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['type'])) {
            return;
        }

        switch ($data['type']) {
            case 'join':
                $this->handleJoin($from, $data);
                break;
                
            case 'content_change':
                $this->handleContentChange($from, $data);
                break;
                
            case 'title_change':
                $this->handleTitleChange($from, $data);
                break;
                
            case 'cursor_position':
                $this->handleCursorPosition($from, $data);
                break;
        }
    }

    public function onClose(ConnectionInterface $connection) {
        $this->clients->detach($connection);
        
        // Remove user from all rooms
        foreach ($this->noteRooms as $noteId => $room) {
            if (isset($room['connections'][$connection->resourceId])) {
                unset($room['connections'][$connection->resourceId]);
                unset($room['users'][$connection->resourceId]);
                $this->broadcastActiveUsers($noteId);
            }
        }
        
        unset($this->userSessions[$connection->resourceId]);
        echo "Connection {$connection->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $connection, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $connection->close();
    }

    private function handleJoin($connection, $data) {
        $noteId = $data['noteId'] ?? null;
        $userId = $data['userId'] ?? null;
        
        if (!$noteId || !$userId) {
            return;
        }
        
        // Verify user has access to this note
        if (!$this->canUserAccessNote($noteId, $userId)) {
            $connection->send(json_encode(['type' => 'error', 'message' => 'Access denied']));
            return;
        }
        
        // Get user info
        $stmt = $this->conn->prepare("SELECT display_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            return;
        }
        
        // Add user to room
        if (!isset($this->noteRooms[$noteId])) {
            $this->noteRooms[$noteId] = ['connections' => [], 'users' => []];
        }
        
        $this->noteRooms[$noteId]['connections'][$connection->resourceId] = $connection;
        $this->noteRooms[$noteId]['users'][$connection->resourceId] = [
            'id' => $userId,
            'name' => $user['display_name']
        ];
        
        $this->userSessions[$connection->resourceId] = [
            'noteId' => $noteId,
            'userId' => $userId
        ];
        
        // Broadcast updated user list
        $this->broadcastActiveUsers($noteId);
        
        echo "User {$userId} joined note {$noteId}\n";
    }

    private function handleContentChange($connection, $data) {
        $noteId = $data['noteId'] ?? null;
        $userId = $data['userId'] ?? null;
        $content = $data['content'] ?? '';
        
        if (!$noteId || !$userId) {
            return;
        }
        
        // Update note content in database
        $stmt = $this->conn->prepare("UPDATE notes SET content = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $content, $noteId);
        $stmt->execute();
        
        // Broadcast to other users in the room
        $this->broadcastToRoom($noteId, [
            'type' => 'content_change',
            'userId' => $userId,
            'content' => $content
        ], $connection->resourceId);
    }

    private function handleTitleChange($connection, $data) {
        $noteId = $data['noteId'] ?? null;
        $userId = $data['userId'] ?? null;
        $title = $data['title'] ?? '';
        
        if (!$noteId || !$userId) {
            return;
        }
        
        // Update note title in database
        $stmt = $this->conn->prepare("UPDATE notes SET title = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $title, $noteId);
        $stmt->execute();
        
        // Broadcast to other users in the room
        $this->broadcastToRoom($noteId, [
            'type' => 'title_change',
            'userId' => $userId,
            'title' => $title
        ], $connection->resourceId);
    }

    private function handleCursorPosition($connection, $data) {
        $noteId = $data['noteId'] ?? null;
        $userId = $data['userId'] ?? null;
        $position = $data['position'] ?? 0;
        
        if (!$noteId || !$userId) {
            return;
        }
        
        // Update cursor position in database (if table exists)
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO note_collaborations (note_id, user_id, cursor_position) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE cursor_position = VALUES(cursor_position), last_active = NOW()
            ");
            $stmt->bind_param("iii", $noteId, $userId, $position);
            $stmt->execute();
        } catch (Exception $e) {
            // Table might not exist yet, ignore for now
        }
        
        // Broadcast cursor position to other users
        $this->broadcastToRoom($noteId, [
            'type' => 'cursor_position',
            'userId' => $userId,
            'position' => $position
        ], $connection->resourceId);
    }

    private function broadcastToRoom($noteId, $message, $excludeConnectionId = null) {
        if (!isset($this->noteRooms[$noteId])) {
            return;
        }
        
        foreach ($this->noteRooms[$noteId]['connections'] as $connId => $connection) {
            if ($connId !== $excludeConnectionId) {
                try {
                    $connection->send(json_encode($message));
                } catch (Exception $e) {
                    echo "Error sending message to connection {$connId}: {$e->getMessage()}\n";
                }
            }
        }
    }

    private function broadcastActiveUsers($noteId) {
        if (!isset($this->noteRooms[$noteId])) {
            return;
        }
        
        $users = array_values($this->noteRooms[$noteId]['users']);
        
        $this->broadcastToRoom($noteId, [
            'type' => 'active_users',
            'users' => $users
        ]);
    }

    private function canUserAccessNote($noteId, $userId) {
        // Check if user owns the note
        $stmt = $this->conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $noteId, $userId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            return true;
        }
        
        // Check if note is shared with user
        $stmt = $this->conn->prepare("
            SELECT permission FROM shared_notes 
            WHERE note_id = ? AND (shared_with_id = ? OR shared_with_email = (SELECT email FROM users WHERE id = ?))
        ");
        $stmt->bind_param("iii", $noteId, $userId, $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() !== null;
    }
}

echo "Starting WebSocket server...\n";
echo "Make sure to create the required database tables first.\n";

try {
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new CollaborationServer($conn)
            )
        ),
        8080
    );

    echo "WebSocket server started on port 8080\n";
    echo "You can now use real-time collaboration features.\n";
    $server->run();
} catch (Exception $e) {
    echo "Error starting server: " . $e->getMessage() . "\n";
}
?>