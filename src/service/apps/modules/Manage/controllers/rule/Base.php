<?php

class Base
{
   
    
    public function __construct()
    {
        $this->employee_id = $_SESSION['employee_id'] ?? '0';
        $this->project_id = $_SESSION['member_project_id'] ?? '0';
    }
}
