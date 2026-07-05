<?php
declare(strict_types=1);

namespace Nemesis\Support;

/**
 * Fluent table builder used by `Table::make()`.
 */
class Table
{
    protected array $headers = [];
    protected array $rows = [];
    protected array $attributes = [];
    protected string $emptyMessage = 'No records found.';

    public static function make(array $headers = [], array $rows = []): static
    {
        $table = new static();
        $table->headers = $headers;
        $table->rows = $rows;
        return $table;
    }

    public function headers(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    public function row(array $row): static
    {
        $this->rows[] = $row;
        return $this;
    }

    public function rows(array $rows): static
    {
        $this->rows = array_merge($this->rows, $rows);
        return $this;
    }

    public function attr(string $name, mixed $value): static
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    public function emptyMessage(string $message): static
    {
        $this->emptyMessage = $message;
        return $this;
    }

    public function render(): string
    {
        $headers = $this->headers;
        if ($headers === [] && isset($this->rows[0]) && is_array($this->rows[0])) {
            $headers = array_keys($this->rows[0]);
        }

        $html = '<table' . $this->renderAttributes($this->attributes) . '>';

        if ($headers !== []) {
            $html .= '<thead><tr>';
            foreach ($headers as $header) {
                $html .= '<th>' . htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8') . '</th>';
            }
            $html .= '</tr></thead>';
        }

        $html .= '<tbody>';

        if ($this->rows === []) {
            $html .= '<tr><td colspan="' . max(1, count($headers)) . '">' . htmlspecialchars($this->emptyMessage, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        } else {
            foreach ($this->rows as $row) {
                $html .= '<tr>';
                if (array_is_list($row)) {
                    foreach ($row as $cell) {
                        $html .= '<td>' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>';
                    }
                } else {
                    foreach ($headers as $header) {
                        $key = (string) $header;
                        $html .= '<td>' . htmlspecialchars((string) ($row[$key] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                    }
                }
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table>';
        return $html;
    }

    public function __toString(): string
    {
        return $this->render();
    }

    protected function renderAttributes(array $attributes): string
    {
        $out = '';
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $out .= ' ' . htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');
                continue;
            }

            $out .= sprintf(
                ' %s="%s"',
                htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
            );
        }

        return $out;
    }
}
