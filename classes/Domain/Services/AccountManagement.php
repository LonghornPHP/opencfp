<?php

declare(strict_types=1);

/**
 * Copyright (c) 2013-2017 OpenCFP
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/opencfp/opencfp
 */

namespace OpenCFP\Domain\Services;

use OpenCFP\Infrastructure\Auth\UserExistsException;
use OpenCFP\Infrastructure\Auth\UserInterface;
use OpenCFP\Infrastructure\Auth\UserNotFoundException;

interface AccountManagement
{
    /**
     * @param int $userId
     *
     * @throws UserNotFoundException
     *
     * @return UserInterface
     */
    public function findById(int $userId): UserInterface;

    /**
     * @param string $email
     *
     * @throws UserNotFoundException
     *
     * @return UserInterface
     */
    public function findByLogin(string $email): UserInterface;

    /**
     * @param string $role
     *
     * @return UserInterface[]
     */
    public function findByRole(string $role): array;

    /**
     * @param string $email
     * @param string $password
     * @param array  $data
     *
     * @throws UserExistsException
     *
     * @return UserInterface
     */
    public function create(string $email, string $password, array $data = []): UserInterface;

    /**
     * @param string $email
     *
     * @throws UserNotFoundException
     */
    public function activate(string $email);

    /**
     * @param string $email
     * @param string $role
     *
     * @throws UserNotFoundException
     */
    public function promoteTo(string $email, string $role);

    /**
     * @param string $email
     * @param string $role
     *
     * @throws UserNotFoundException
     */
    public function demoteFrom(string $email, string $role);
}
