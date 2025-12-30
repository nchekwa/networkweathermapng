<?php
/**
 * Zabbix Weathermap - Authentication Controller
 */

declare(strict_types=1);

namespace App\Controllers;

class AuthController extends BaseController
{
    public function loginForm(array $params): void
    {
        if ($this->auth->check()) {
            $this->redirect('/');
            return;
        }
        
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);
        
        $this->render('auth/login', [
            'error' => $error,
            'title' => 'Login',
        ], false);
    }
    
    public function login(array $params): void
    {
        $username = $this->getInput('username', '');
        $password = $this->getInput('password', '');
        
        if (empty($username) || empty($password)) {
            $_SESSION['login_error'] = 'Please enter username and password';
            $this->redirect('/login');
            return;
        }
        
        if ($this->auth->attempt($username, $password)) {
            $this->redirect('/');
        } else {
            $_SESSION['login_error'] = 'Invalid username or password';
            $this->redirect('/login');
        }
    }
    
    public function logout(array $params): void
    {
        $this->auth->logout();
        $this->redirect('/login');
    }
}
