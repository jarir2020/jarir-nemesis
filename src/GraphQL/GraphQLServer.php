<?php
declare(strict_types=1);

namespace Nemesis\GraphQL;

class GraphQLServer {
    protected $schema;
    protected $resolvers = [];

    public function setSchema($schema) {
        $this->schema = $schema;
        return $this;
    }

    public function addResolver($type, $field, $resolver) {
        if (!isset($this->resolvers[$type])) {
            $this->resolvers[$type] = [];
        }
        $this->resolvers[$type][$field] = $resolver;
        return $this;
    }

    public function handleRequest() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $query = $input['query'] ?? null;
        $variables = $input['variables'] ?? [];

        if (!$query) {
            return $this->error('No query provided');
        }

        try {
            $result = $this->execute($query, $variables);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    protected function execute($query, $variables) {
        // Basic GraphQL parser (simplified - real implementation would use a proper parser)
        // This is a minimal implementation to demonstrate the concept
        
        $query = trim($query);
        
        // Extract operation type and fields
        if (preg_match('/^query\s+\{([^}]+)\}/', $query, $matches)) {
            $fields = trim($matches[1]);
            return $this->resolveQuery($fields, $variables);
        }

        if (preg_match('/^mutation\s+\{([^}]+)\}/', $query, $matches)) {
            $fields = trim($matches[1]);
            return $this->resolveMutation($fields, $variables);
        }

        throw new \Exception('Invalid GraphQL query');
    }

    protected function resolveQuery($fields, $variables) {
        // Simplified resolver
        $data = [];
        
        // In a real implementation, you'd parse the fields properly
        // and call the appropriate resolvers
        
        return $data;
    }

    protected function resolveMutation($fields, $variables) {
        // Simplified mutation resolver
        return [];
    }

    protected function success($data) {
        header('Content-Type: application/json');
        echo json_encode(['data' => $data]);
        exit;
    }

    protected function error($message) {
        header('Content-Type: application/json');
        echo json_encode(['errors' => [['message' => $message]]]);
        exit;
    }
}
