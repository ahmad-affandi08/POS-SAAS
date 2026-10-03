import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:inti/Inti.dart';

import 'TeksUang.dart';

/// Hitung uang laci per pecahan (buka & tutup shift, PRD F-06/F-11): satu baris per nominal dengan tombol kurang/tambah
/// (target sentuh ≥ 48dp) dan jumlah lembar/keping. [jumlah] = lembar per nominal; nominal yang tidak ada = 0.
/// Pemakai menyimpan keadaan dan menerima perubahan lewat [saatBerubah] (nominal, jumlah baru ≥ 0).
/// Audit kemudahan pakai #28: jumlah lembar bisa diketik langsung (37 lembar tidak perlu 37 ketukan) dan ada tombol
/// "+10" untuk segepok.
class HitungPecahan extends StatelessWidget {
  const HitungPecahan({super.key, required this.nominal, required this.jumlah, required this.saatBerubah});

  /// Batas lembar per nominal (sama dengan validasi server).
  static const int jumlahMaksimum = 100000;

  final List<int> nominal;
  final Map<int, int> jumlah;
  final void Function(int nominal, int jumlahBaru) saatBerubah;

  /// Total uang dari hitungan per pecahan.
  static Uang HitungTotal(Map<int, int> jumlah) =>
      jumlah.entries.fold(Uang.Nol(), (t, e) => t.Tambah(Uang.DariBulat(e.key).Kali(Decimal.fromInt(e.value))));

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final n in nominal)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 2),
            child: _BarisPecahan(nominal: n, jumlah: jumlah[n] ?? 0, saatBerubah: (baru) => saatBerubah(n, baru)),
          ),
      ],
    );
  }
}

class _BarisPecahan extends StatefulWidget {
  const _BarisPecahan({required this.nominal, required this.jumlah, required this.saatBerubah});

  final int nominal;
  final int jumlah;
  final ValueChanged<int> saatBerubah;

  @override
  State<_BarisPecahan> createState() => _StatusBarisPecahan();
}

class _StatusBarisPecahan extends State<_BarisPecahan> {
  late final TextEditingController _isian = TextEditingController(text: '${widget.jumlah}');

  /// Nilai terakhir yang dilaporkan: dua ketukan beruntun sebelum induk membangun ulang tetap terhitung dua.
  late int _nilai = widget.jumlah;

  int _NilaiIsian() => int.tryParse(_isian.text) ?? 0;

  @override
  void didUpdateWidget(covariant _BarisPecahan lama) {
    super.didUpdateWidget(lama);
    _nilai = widget.jumlah;
    // Isian kosong saat diketik = 0; jangan menimpanya dengan "0" selama nilainya sama.
    if (_NilaiIsian() != widget.jumlah) {
      _isian.text = '${widget.jumlah}';
    }
  }

  @override
  void dispose() {
    _isian.dispose();
    super.dispose();
  }

  void _Ubah(int baru) {
    _nilai = baru.clamp(0, HitungPecahan.jumlahMaksimum);
    widget.saatBerubah(_nilai);
  }

  @override
  Widget build(BuildContext context) {
    final label = Uang.DariBulat(widget.nominal).FormatRupiah();
    return Row(
      children: [
        Expanded(child: TeksUang(Uang.DariBulat(widget.nominal), rataKanan: false)),
        IconButton(
          tooltip: 'Kurangi $label',
          onPressed: widget.jumlah > 0 ? () => _Ubah(_nilai - 1) : null,
          icon: const Icon(Icons.remove),
        ),
        SizedBox(
          width: 64,
          child: TextField(
            controller: _isian,
            keyboardType: TextInputType.number,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)],
            textAlign: TextAlign.center,
            style: const TextStyle(fontFeatures: [FontFeature.tabularFigures()]),
            decoration: InputDecoration(
              isDense: true,
              hintText: '0',
              contentPadding: const EdgeInsets.symmetric(horizontal: 4, vertical: 12),
              border: const OutlineInputBorder(),
              semanticCounterText: 'Jumlah lembar $label',
            ),
            onTap: () => _isian.selection = TextSelection(baseOffset: 0, extentOffset: _isian.text.length),
            onChanged: (teks) => _Ubah(int.tryParse(teks) ?? 0),
          ),
        ),
        IconButton(
          tooltip: 'Tambah $label',
          onPressed: widget.jumlah < HitungPecahan.jumlahMaksimum ? () => _Ubah(_nilai + 1) : null,
          icon: const Icon(Icons.add),
        ),
        SizedBox(
          width: 52,
          height: 48,
          child: TextButton(
            style: TextButton.styleFrom(padding: EdgeInsets.zero, minimumSize: const Size(48, 48)),
            onPressed: widget.jumlah < HitungPecahan.jumlahMaksimum ? () => _Ubah(_nilai + 10) : null,
            child: Semantics(label: 'Tambah 10 lembar $label', excludeSemantics: true, child: const Text('+10')),
          ),
        ),
      ],
    );
  }
}
