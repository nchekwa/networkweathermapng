<?php
/**
 * NetworkWeathermapNG - Account Controller
 */

declare(strict_types=1);

namespace App\Controllers;

class AccountController extends BaseController
{
    public function changePasswordForm(array $params): void
    {
        $this->requireAuth();

        $this->render('account/change_password', [
            'title' => 'Change Password',
        ]);
    }

    public function changePassword(array $params): void
    {
        $this->requireAuth();

        $currentPassword = (string) $this->getInput('current_password', '');
        $newPassword = (string) $this->getInput('new_password', '');
        $confirmPassword = (string) $this->getInput('confirm_password', '');

        if ($newPassword === '' || $confirmPassword === '') {
            $this->flash('error', 'New password and confirmation are required');
            $this->redirect('/account/password');
            return;
        }

        if ($newPassword !== $confirmPassword) {
            $this->flash('error', 'New password and confirmation do not match');
            $this->redirect('/account/password');
            return;
        }

        if (strlen($newPassword) < 8) {
            $this->flash('error', 'New password must be at least 8 characters');
            $this->redirect('/account/password');
            return;
        }

        $user = $this->auth->user();
        $userId = (int) ($user['id'] ?? 0);

        $dbUser = $this->database->queryOne(
            'SELECT id, password_hash FROM users WHERE id = ? AND active = 1',
            [$userId]
        );

        if (!$dbUser) {
            $this->flash('error', 'User not found');
            $this->redirect('/account/password');
            return;
        }

        if (!password_verify($currentPassword, $dbUser['password_hash'])) {
            $this->flash('error', 'Current password is incorrect');
            $this->redirect('/account/password');
            return;
        }

        $this->database->update('users', [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ], 'id = ?', [$userId]);

        $this->flash('success', 'Password changed successfully');
        $this->redirect('/account/password');
    }
}
