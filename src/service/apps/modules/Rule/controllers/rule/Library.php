<?php


class Library extends Base
{

    public function list($input = [])
    {
        $params = $this->parameters([
            'page' => [self::T_INT],
            'pageSize' => [self::T_INT],
        ], $input);

        $result = $this->get('/library', $params);
        $this->success($result ?: []);

    }


    public function detail($input)
    {
        $params = $this->parameters([
            '_id' => [self::T_STRING, true],
        ], $input);

        $result = $this->get('/library/' . $params['_id'], $params);
        $this->success($result);
    }


    public function create($input = [])
    {
        $params = $this->parameters([
            'kind' => [self::T_STRING, true],
            'name' => [self::T_STRING, true],
            'group' => [self::T_STRING, true],
            'description' => [self::T_STRING, true],
            'items' => [self::T_JSON, true],
        ], $input);

        $result = $this->post('/library/create', $params);
        $this->success($result);
    }


    public function modify($input)
    {
        $params = $this->parameters([
            '_id' => [self::T_STRING, true],
            'kind' => [self::T_STRING, true],
            'name' => [self::T_STRING],
            'group' => [self::T_STRING],
            'description' => [self::T_STRING],
            'enabled' => [self::T_BOOL],
            'items' => [self::T_JSON],
        ], $input);
        $_id = $params['_id'];
        unset($params['_id']);
        $result = $this->post("/library/modify/{$_id}", $params);
        $this->success($result);
    }

}