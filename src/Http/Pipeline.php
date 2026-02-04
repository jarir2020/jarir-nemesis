<?php

namespace Nemesis\Http;

class Pipeline {
    protected $request;
    protected $middleware = [];

    public function send($request) {
        $this->request = $request;
        return $this;
    }

    public function through($middleware) {
        $this->middleware = is_array($middleware) ? $middleware : func_get_args();
        return $this;
    }

    public function then(\Closure $destination) {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            $this->carry(),
            $this->destination($destination)
        );

        return $pipeline($this->request);
    }

    protected function carry() {
        return function ($stack, $pipe) {
            return function ($request) use ($stack, $pipe) {
                if (is_string($pipe)) {
                    $pipe = new $pipe();
                }

                if (is_callable($pipe)) {
                    return $pipe($request, $stack);
                }

                if (method_exists($pipe, 'handle')) {
                    return $pipe->handle($request, $stack);
                }

                return $stack($request);
            };
        };
    }

    protected function destination(\Closure $destination) {
        return function ($request) use ($destination) {
            return $destination($request);
        };
    }
}
