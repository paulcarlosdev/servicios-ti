<?php

namespace App\Policies;

use App\Models\User;

/** Aplica la matriz User::PERMISOS: "V" permite ver, "E" además crear, editar y eliminar. */
abstract class PoliticaPorModulo
{
    protected string $modulo;

    public function viewAny(User $user): bool
    {
        return $user->puedeVer($this->modulo);
    }

    public function view(User $user): bool
    {
        return $user->puedeVer($this->modulo);
    }

    public function create(User $user): bool
    {
        return $user->puedeEditar($this->modulo);
    }

    public function update(User $user): bool
    {
        return $user->puedeEditar($this->modulo);
    }

    public function delete(User $user): bool
    {
        return $user->puedeEditar($this->modulo);
    }
}
