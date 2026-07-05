<?php
declare(strict_types=1);

namespace Nemesis\Support;

/**
 * Fluent form builder used by `Form::make()`.
 */
class Form
{
    protected string $action;
    protected string $method;
    protected array $attributes = [];
    protected array $fields = [];

    public function __construct(string $action = '', string $method = 'POST')
    {
        $this->action = $action;
        $this->method = strtoupper($method);
    }

    public static function make(string $action = '', string $method = 'POST'): static
    {
        return new static($action, $method);
    }

    public function action(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function method(string $method): static
    {
        $this->method = strtoupper($method);
        return $this;
    }

    public function attr(string $name, mixed $value): static
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    public function text(string $name, mixed $value = '', array $attributes = []): static
    {
        return $this->input('text', $name, $value, $attributes);
    }

    public function email(string $name, mixed $value = '', array $attributes = []): static
    {
        return $this->input('email', $name, $value, $attributes);
    }

    public function password(string $name, array $attributes = []): static
    {
        return $this->input('password', $name, '', $attributes);
    }

    public function hidden(string $name, mixed $value = '', array $attributes = []): static
    {
        return $this->input('hidden', $name, $value, $attributes);
    }

    public function checkbox(string $name, mixed $value = '1', bool $checked = false, array $attributes = []): static
    {
        $attributes['checked'] = $checked;
        return $this->input('checkbox', $name, $value, $attributes);
    }

    public function textarea(string $name, mixed $value = '', array $attributes = []): static
    {
        $this->fields[] = [
            'type' => 'textarea',
            'name' => $name,
            'value' => $value,
            'attributes' => $attributes,
        ];

        return $this;
    }

    public function select(string $name, array $options, mixed $selected = null, array $attributes = []): static
    {
        $this->fields[] = [
            'type' => 'select',
            'name' => $name,
            'options' => $options,
            'selected' => $selected,
            'attributes' => $attributes,
        ];

        return $this;
    }

    public function submit(string $label = 'Submit', array $attributes = []): static
    {
        $this->fields[] = [
            'type' => 'submit',
            'label' => $label,
            'attributes' => $attributes,
        ];

        return $this;
    }

    public function input(string $type, string $name, mixed $value = '', array $attributes = []): static
    {
        $this->fields[] = [
            'type' => strtolower($type),
            'name' => $name,
            'value' => $value,
            'attributes' => $attributes,
        ];

        return $this;
    }

    public function render(): string
    {
        $formMethod = $this->method === 'GET' ? 'GET' : 'POST';
        $attributes = array_merge(['method' => $formMethod, 'action' => $this->action], $this->attributes);
        $html = '<form' . $this->renderAttributes($attributes) . '>';

        if (in_array($this->method, ['PUT', 'PATCH', 'DELETE'], true)) {
            $html .= "\n<input type=\"hidden\" name=\"_method\" value=\"" . htmlspecialchars($this->method, ENT_QUOTES, 'UTF-8') . "\">";
        }

        foreach ($this->fields as $field) {
            $html .= "\n" . $this->renderField($field);
        }

        $html .= "\n</form>";
        return $html;
    }

    public function __toString(): string
    {
        return $this->render();
    }

    protected function renderField(array $field): string
    {
        $type = $field['type'] ?? 'text';
        return match ($type) {
            'textarea' => sprintf(
                '<textarea name="%s"%s>%s</textarea>',
                htmlspecialchars((string) $field['name'], ENT_QUOTES, 'UTF-8'),
                $this->renderAttributes($field['attributes'] ?? []),
                htmlspecialchars((string) ($field['value'] ?? ''), ENT_QUOTES, 'UTF-8')
            ),
            'select' => $this->renderSelect($field),
            'submit' => sprintf(
                '<button type="submit"%s>%s</button>',
                $this->renderAttributes($field['attributes'] ?? []),
                htmlspecialchars((string) ($field['label'] ?? 'Submit'), ENT_QUOTES, 'UTF-8')
            ),
            default => sprintf(
                '<input type="%s" name="%s" value="%s"%s>',
                htmlspecialchars($type, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $field['name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) ($field['value'] ?? ''), ENT_QUOTES, 'UTF-8'),
                $this->renderAttributes($field['attributes'] ?? [])
            ),
        };
    }

    protected function renderSelect(array $field): string
    {
        $html = sprintf(
            '<select name="%s"%s>',
            htmlspecialchars((string) $field['name'], ENT_QUOTES, 'UTF-8'),
            $this->renderAttributes($field['attributes'] ?? [])
        );

        foreach (($field['options'] ?? []) as $value => $label) {
            $selected = ((string) $value === (string) ($field['selected'] ?? null)) ? ' selected' : '';
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'),
                $selected,
                htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8')
            );
        }

        $html .= '</select>';
        return $html;
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
