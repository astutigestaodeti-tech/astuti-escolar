<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DemonstrationController extends Controller
{
    public static function enabled(): bool
    {
        return app()->environment('testing') || (app()->environment('local') && (bool) config('app.demonstracao'));
    }

    public function index(Request $request): View
    {
        // Protótipo visual antigo: só no notebook (ASTUTI_DEMONSTRACAO=1). Nunca na VPS publicada.
        abort_unless(self::enabled(), 404);

        $screens = [
            'painel' => 'Visão geral',
            'escolas' => 'Escolas',
            'alunos' => 'Alunos',
            'matriculas' => 'Matrículas',
            'importacoes' => 'Importações',
            'documentos' => 'Documentos',
            'indicadores' => 'Indicadores',
        ];

        $screen = $request->query('tela', 'painel');
        abort_unless(is_string($screen) && array_key_exists($screen, $screens), 404);

        $schools = [
            ['name' => 'EMEF Caminhos do Saber', 'type' => 'Ensino Fundamental', 'students' => 462, 'classes' => 16, 'area' => 'Centro', 'initial' => 'CS', 'tone' => 'blue'],
            ['name' => 'EMEI Jardim das Flores', 'type' => 'Educação Infantil', 'students' => 308, 'classes' => 12, 'area' => 'Jardim das Flores', 'initial' => 'JF', 'tone' => 'peach'],
            ['name' => 'EMEF Rio Claro', 'type' => 'Ensino Fundamental', 'students' => 354, 'classes' => 13, 'area' => 'Vila do Rio', 'initial' => 'RC', 'tone' => 'mint'],
            ['name' => 'CEI Pequenos Horizontes', 'type' => 'Creche', 'students' => 160, 'classes' => 7, 'area' => 'Jardim do Sol', 'initial' => 'PH', 'tone' => 'purple'],
        ];

        $students = [
            ['name' => 'Marina Alves', 'initial' => 'MA', 'id' => 'MS-2026-001', 'school' => 'EMEF Caminhos do Saber', 'class' => '5º ano A', 'status' => 'Ativa'],
            ['name' => 'Lucas Ferreira', 'initial' => 'LF', 'id' => 'MS-2026-002', 'school' => 'EMEF Rio Claro', 'class' => '3º ano B', 'status' => 'Ativa'],
            ['name' => 'Sofia Martins', 'initial' => 'SM', 'id' => 'MS-2026-003', 'school' => 'EMEI Jardim das Flores', 'class' => 'Pré II', 'status' => 'Ativa'],
            ['name' => 'Pedro Oliveira', 'initial' => 'PO', 'id' => 'MS-2026-004', 'school' => 'EMEF Caminhos do Saber', 'class' => '4º ano A', 'status' => 'Em conferência'],
            ['name' => 'Ana Clara Rocha', 'initial' => 'AR', 'id' => 'MS-2026-005', 'school' => 'CEI Pequenos Horizontes', 'class' => 'Maternal II', 'status' => 'Ativa'],
        ];

        $searchQuery = $request->query('busca', '');
        $search = is_string($searchQuery) ? mb_substr(trim($searchQuery), 0, 80) : '';
        if ($screen === 'alunos' && $search !== '') {
            $students = array_values(array_filter($students, fn (array $student): bool => mb_stripos($student['name'], $search) !== false || mb_stripos($student['school'], $search) !== false));
        }

        return view('demo.painel', compact('screen', 'screens', 'schools', 'students', 'search'));
    }
}
