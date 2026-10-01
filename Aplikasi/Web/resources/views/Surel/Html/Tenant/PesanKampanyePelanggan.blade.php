@extends('Surel.TataLetak')

@section('Judul', 'Pesan dari '.$NamaUsaha)
@section('Pratinjau', 'Pesan dari '.$NamaUsaha.'.')

@section('Isi')
    {{-- Isi kampanye disusun penjual di back-office (CRM-07), jadi ditampilkan apa adanya sebagai kutipan. --}}
    @include('Surel.Komponen.Kutipan', ['Teks' => $Isi])
@endsection

@section('CatatanKaki', 'Email ini dikirim oleh '.$NamaUsaha.' karena Anda setuju menerima kabar promosi. Berhenti berlangganan: '.$TautanBerhenti)
