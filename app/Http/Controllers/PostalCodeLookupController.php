<?php

namespace App\Http\Controllers;

use App\Address\PostalCodeBook;
use Illuminate\Http\JsonResponse;

/** Consulta de CEP na base local da Prefeitura, usada pelo formulário do aluno. */
class PostalCodeLookupController extends Controller
{
    public function show(PostalCodeBook $book, string $municipio, string $cep): JsonResponse
    {
        if (PostalCodeBook::normalize($cep) === null) {
            return response()->json(['encontrado' => false, 'mensagem' => 'O CEP tem 8 números.'], 422);
        }

        $found = $book->find($cep);

        return response()->json($found === null
            ? ['encontrado' => false, 'mensagem' => 'CEP não encontrado na base desta Prefeitura. Preencha os campos abaixo.']
            : ['encontrado' => true] + $found)
            ->header('Cache-Control', 'private, max-age=300');
    }
}
