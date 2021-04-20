<?php

class Decision extends Base
{

    public function list($input)
    {
        $params = $this->parameters([
            '_id' => [self::T_JSON],
            'kind' => [self::T_STRING],
            'keyword' => [self::T_STRING],
            'rules' => [self::T_BOOL],
            'page' => [self::T_INT],
            'pageSize' => [self::T_INT],
        ], $input);

        $result = $this->post('/decision', $params);
        $this->success($result ?: []);
    }


    public function create($input)
    {
        $params = $this->parameters([
            'kind' => [self::T_INT, true],
            'name' => [self::T_STRING, true],
            'description' => [self::T_STRING, true],
            'rules' => [self::T_JSON, true],
            'effectiveAt' => [self::T_DATE],
            'expiryAt' => [self::T_DATE],
            'enabled' => [self::T_BOOL],
        ], $input);

        array_map(function ($rule) {
            $this->parameters([
                'name' => [self::T_STRING, true],
                'description' => [self::T_STRING, true],
                'priority' => [self::T_INT],
                'if' => [self::T_JSON, true],
                'then' => [self::T_JSON, true],
                'else' => [self::T_JSON],
                'effectiveAt' => [self::T_DATE],
                'expiryAt' => [self::T_DATE],
                'enabled' => [self::T_BOOL],
            ], $rule);
        }, $params['rules']);

        $params['sid'] = $params['sid'] ?? resource_id_generator(self::RESOURCE_TYPES['rule']);
        $result = $this->post('/decision/create', $params);
        $this->success($result);
    }


    public function modify($input)
    {


    }


    public function exec($input)
    {
        $params = $this->parameters([
            '_id' => [self::T_STRING, true],
            'facts' => [self::T_JSON, true],
        ], $input);

        $result = $this->post('/decision/exec', $params);
        $this->success($result);

    }
}