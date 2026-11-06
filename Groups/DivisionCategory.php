<?php

namespace Okay\Modules\Sviat\NovaPoshta\Groups;

/** `divisionCategory` of the Nova Poshta dictionary (measured 05.11.2026). */
enum DivisionCategory: string
{
    case PostBranch = 'PostBranch';
    case CargoBranch = 'CargoBranch';
    case PUDO = 'PUDO';
    case Postomat = 'Postomat';
}
