@extends('errors.layout')
@section('codigo', '403')
@section('titulo', 'Acesso não autorizado')
@section('mensagem', $exception->getMessage() ?: 'Não tem permissão para aceder a esta funcionalidade. Se precisar de acesso, contacte o administrador do sistema.')
