// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'BasisDataKasir.dart';

// ignore_for_file: type=lint
class $PengaturanTable extends Pengaturan with TableInfo<$PengaturanTable, BarisPengaturan> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $PengaturanTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _KunciMeta = const VerificationMeta('Kunci');
  @override
  late final GeneratedColumn<String> Kunci = GeneratedColumn<String>(
    'Kunci',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _NilaiMeta = const VerificationMeta('Nilai');
  @override
  late final GeneratedColumn<String> Nilai = GeneratedColumn<String>(
    'Nilai',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [Kunci, Nilai];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'Pengaturan';
  @override
  VerificationContext validateIntegrity(Insertable<BarisPengaturan> instance, {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('Kunci')) {
      context.handle(_KunciMeta, Kunci.isAcceptableOrUnknown(data['Kunci']!, _KunciMeta));
    } else if (isInserting) {
      context.missing(_KunciMeta);
    }
    if (data.containsKey('Nilai')) {
      context.handle(_NilaiMeta, Nilai.isAcceptableOrUnknown(data['Nilai']!, _NilaiMeta));
    } else if (isInserting) {
      context.missing(_NilaiMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {Kunci};
  @override
  BarisPengaturan map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return BarisPengaturan(
      Kunci: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Kunci'])!,
      Nilai: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Nilai'])!,
    );
  }

  @override
  $PengaturanTable createAlias(String alias) {
    return $PengaturanTable(attachedDatabase, alias);
  }
}

class BarisPengaturan extends DataClass implements Insertable<BarisPengaturan> {
  final String Kunci;
  final String Nilai;
  const BarisPengaturan({required this.Kunci, required this.Nilai});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['Kunci'] = Variable<String>(Kunci);
    map['Nilai'] = Variable<String>(Nilai);
    return map;
  }

  PengaturanCompanion toCompanion(bool nullToAbsent) {
    return PengaturanCompanion(Kunci: Value(Kunci), Nilai: Value(Nilai));
  }

  factory BarisPengaturan.fromJson(Map<String, dynamic> json, {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return BarisPengaturan(
      Kunci: serializer.fromJson<String>(json['Kunci']),
      Nilai: serializer.fromJson<String>(json['Nilai']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{'Kunci': serializer.toJson<String>(Kunci), 'Nilai': serializer.toJson<String>(Nilai)};
  }

  BarisPengaturan copyWith({String? Kunci, String? Nilai}) =>
      BarisPengaturan(Kunci: Kunci ?? this.Kunci, Nilai: Nilai ?? this.Nilai);
  BarisPengaturan copyWithCompanion(PengaturanCompanion data) {
    return BarisPengaturan(
      Kunci: data.Kunci.present ? data.Kunci.value : this.Kunci,
      Nilai: data.Nilai.present ? data.Nilai.value : this.Nilai,
    );
  }

  @override
  String toString() {
    return (StringBuffer('BarisPengaturan(')
          ..write('Kunci: $Kunci, ')
          ..write('Nilai: $Nilai')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(Kunci, Nilai);
  @override
  bool operator ==(Object other) =>
      identical(this, other) || (other is BarisPengaturan && other.Kunci == this.Kunci && other.Nilai == this.Nilai);
}

class PengaturanCompanion extends UpdateCompanion<BarisPengaturan> {
  final Value<String> Kunci;
  final Value<String> Nilai;
  final Value<int> rowid;
  const PengaturanCompanion({
    this.Kunci = const Value.absent(),
    this.Nilai = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  PengaturanCompanion.insert({required String Kunci, required String Nilai, this.rowid = const Value.absent()})
    : Kunci = Value(Kunci),
      Nilai = Value(Nilai);
  static Insertable<BarisPengaturan> custom({
    Expression<String>? Kunci,
    Expression<String>? Nilai,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (Kunci != null) 'Kunci': Kunci,
      if (Nilai != null) 'Nilai': Nilai,
      if (rowid != null) 'rowid': rowid,
    });
  }

  PengaturanCompanion copyWith({Value<String>? Kunci, Value<String>? Nilai, Value<int>? rowid}) {
    return PengaturanCompanion(Kunci: Kunci ?? this.Kunci, Nilai: Nilai ?? this.Nilai, rowid: rowid ?? this.rowid);
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (Kunci.present) {
      map['Kunci'] = Variable<String>(Kunci.value);
    }
    if (Nilai.present) {
      map['Nilai'] = Variable<String>(Nilai.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('PengaturanCompanion(')
          ..write('Kunci: $Kunci, ')
          ..write('Nilai: $Nilai, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $StafTable extends Staf with TableInfo<$StafTable, BarisStaf> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $StafTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _UuidMeta = const VerificationMeta('Uuid');
  @override
  late final GeneratedColumn<String> Uuid = GeneratedColumn<String>(
    'Uuid',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _NamaMeta = const VerificationMeta('Nama');
  @override
  late final GeneratedColumn<String> Nama = GeneratedColumn<String>(
    'Nama',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _PemilikMeta = const VerificationMeta('Pemilik');
  @override
  late final GeneratedColumn<bool> Pemilik = GeneratedColumn<bool>(
    'Pemilik',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: true,
    defaultConstraints: GeneratedColumn.constraintIsAlways('CHECK ("Pemilik" IN (0, 1))'),
  );
  static const VerificationMeta _IzinMeta = const VerificationMeta('Izin');
  @override
  late final GeneratedColumn<String> Izin = GeneratedColumn<String>(
    'Izin',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _PinDiaturMeta = const VerificationMeta('PinDiatur');
  @override
  late final GeneratedColumn<bool> PinDiatur = GeneratedColumn<bool>(
    'PinDiatur',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: true,
    defaultConstraints: GeneratedColumn.constraintIsAlways('CHECK ("PinDiatur" IN (0, 1))'),
  );
  static const VerificationMeta _PinGaramMeta = const VerificationMeta('PinGaram');
  @override
  late final GeneratedColumn<String> PinGaram = GeneratedColumn<String>(
    'PinGaram',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _PinNonceMeta = const VerificationMeta('PinNonce');
  @override
  late final GeneratedColumn<String> PinNonce = GeneratedColumn<String>(
    'PinNonce',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _PinSandiMeta = const VerificationMeta('PinSandi');
  @override
  late final GeneratedColumn<String> PinSandi = GeneratedColumn<String>(
    'PinSandi',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [Uuid, Nama, Pemilik, Izin, PinDiatur, PinGaram, PinNonce, PinSandi];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'Staf';
  @override
  VerificationContext validateIntegrity(Insertable<BarisStaf> instance, {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('Uuid')) {
      context.handle(_UuidMeta, Uuid.isAcceptableOrUnknown(data['Uuid']!, _UuidMeta));
    } else if (isInserting) {
      context.missing(_UuidMeta);
    }
    if (data.containsKey('Nama')) {
      context.handle(_NamaMeta, Nama.isAcceptableOrUnknown(data['Nama']!, _NamaMeta));
    } else if (isInserting) {
      context.missing(_NamaMeta);
    }
    if (data.containsKey('Pemilik')) {
      context.handle(_PemilikMeta, Pemilik.isAcceptableOrUnknown(data['Pemilik']!, _PemilikMeta));
    } else if (isInserting) {
      context.missing(_PemilikMeta);
    }
    if (data.containsKey('Izin')) {
      context.handle(_IzinMeta, Izin.isAcceptableOrUnknown(data['Izin']!, _IzinMeta));
    } else if (isInserting) {
      context.missing(_IzinMeta);
    }
    if (data.containsKey('PinDiatur')) {
      context.handle(_PinDiaturMeta, PinDiatur.isAcceptableOrUnknown(data['PinDiatur']!, _PinDiaturMeta));
    } else if (isInserting) {
      context.missing(_PinDiaturMeta);
    }
    if (data.containsKey('PinGaram')) {
      context.handle(_PinGaramMeta, PinGaram.isAcceptableOrUnknown(data['PinGaram']!, _PinGaramMeta));
    }
    if (data.containsKey('PinNonce')) {
      context.handle(_PinNonceMeta, PinNonce.isAcceptableOrUnknown(data['PinNonce']!, _PinNonceMeta));
    }
    if (data.containsKey('PinSandi')) {
      context.handle(_PinSandiMeta, PinSandi.isAcceptableOrUnknown(data['PinSandi']!, _PinSandiMeta));
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {Uuid};
  @override
  BarisStaf map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return BarisStaf(
      Uuid: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Uuid'])!,
      Nama: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Nama'])!,
      Pemilik: attachedDatabase.typeMapping.read(DriftSqlType.bool, data['${effectivePrefix}Pemilik'])!,
      Izin: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Izin'])!,
      PinDiatur: attachedDatabase.typeMapping.read(DriftSqlType.bool, data['${effectivePrefix}PinDiatur'])!,
      PinGaram: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}PinGaram']),
      PinNonce: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}PinNonce']),
      PinSandi: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}PinSandi']),
    );
  }

  @override
  $StafTable createAlias(String alias) {
    return $StafTable(attachedDatabase, alias);
  }
}

class BarisStaf extends DataClass implements Insertable<BarisStaf> {
  final String Uuid;
  final String Nama;
  final bool Pemilik;
  final String Izin;
  final bool PinDiatur;
  final String? PinGaram;
  final String? PinNonce;
  final String? PinSandi;
  const BarisStaf({
    required this.Uuid,
    required this.Nama,
    required this.Pemilik,
    required this.Izin,
    required this.PinDiatur,
    this.PinGaram,
    this.PinNonce,
    this.PinSandi,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['Uuid'] = Variable<String>(Uuid);
    map['Nama'] = Variable<String>(Nama);
    map['Pemilik'] = Variable<bool>(Pemilik);
    map['Izin'] = Variable<String>(Izin);
    map['PinDiatur'] = Variable<bool>(PinDiatur);
    if (!nullToAbsent || PinGaram != null) {
      map['PinGaram'] = Variable<String>(PinGaram);
    }
    if (!nullToAbsent || PinNonce != null) {
      map['PinNonce'] = Variable<String>(PinNonce);
    }
    if (!nullToAbsent || PinSandi != null) {
      map['PinSandi'] = Variable<String>(PinSandi);
    }
    return map;
  }

  StafCompanion toCompanion(bool nullToAbsent) {
    return StafCompanion(
      Uuid: Value(Uuid),
      Nama: Value(Nama),
      Pemilik: Value(Pemilik),
      Izin: Value(Izin),
      PinDiatur: Value(PinDiatur),
      PinGaram: PinGaram == null && nullToAbsent ? const Value.absent() : Value(PinGaram),
      PinNonce: PinNonce == null && nullToAbsent ? const Value.absent() : Value(PinNonce),
      PinSandi: PinSandi == null && nullToAbsent ? const Value.absent() : Value(PinSandi),
    );
  }

  factory BarisStaf.fromJson(Map<String, dynamic> json, {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return BarisStaf(
      Uuid: serializer.fromJson<String>(json['Uuid']),
      Nama: serializer.fromJson<String>(json['Nama']),
      Pemilik: serializer.fromJson<bool>(json['Pemilik']),
      Izin: serializer.fromJson<String>(json['Izin']),
      PinDiatur: serializer.fromJson<bool>(json['PinDiatur']),
      PinGaram: serializer.fromJson<String?>(json['PinGaram']),
      PinNonce: serializer.fromJson<String?>(json['PinNonce']),
      PinSandi: serializer.fromJson<String?>(json['PinSandi']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'Uuid': serializer.toJson<String>(Uuid),
      'Nama': serializer.toJson<String>(Nama),
      'Pemilik': serializer.toJson<bool>(Pemilik),
      'Izin': serializer.toJson<String>(Izin),
      'PinDiatur': serializer.toJson<bool>(PinDiatur),
      'PinGaram': serializer.toJson<String?>(PinGaram),
      'PinNonce': serializer.toJson<String?>(PinNonce),
      'PinSandi': serializer.toJson<String?>(PinSandi),
    };
  }

  BarisStaf copyWith({
    String? Uuid,
    String? Nama,
    bool? Pemilik,
    String? Izin,
    bool? PinDiatur,
    Value<String?> PinGaram = const Value.absent(),
    Value<String?> PinNonce = const Value.absent(),
    Value<String?> PinSandi = const Value.absent(),
  }) => BarisStaf(
    Uuid: Uuid ?? this.Uuid,
    Nama: Nama ?? this.Nama,
    Pemilik: Pemilik ?? this.Pemilik,
    Izin: Izin ?? this.Izin,
    PinDiatur: PinDiatur ?? this.PinDiatur,
    PinGaram: PinGaram.present ? PinGaram.value : this.PinGaram,
    PinNonce: PinNonce.present ? PinNonce.value : this.PinNonce,
    PinSandi: PinSandi.present ? PinSandi.value : this.PinSandi,
  );
  BarisStaf copyWithCompanion(StafCompanion data) {
    return BarisStaf(
      Uuid: data.Uuid.present ? data.Uuid.value : this.Uuid,
      Nama: data.Nama.present ? data.Nama.value : this.Nama,
      Pemilik: data.Pemilik.present ? data.Pemilik.value : this.Pemilik,
      Izin: data.Izin.present ? data.Izin.value : this.Izin,
      PinDiatur: data.PinDiatur.present ? data.PinDiatur.value : this.PinDiatur,
      PinGaram: data.PinGaram.present ? data.PinGaram.value : this.PinGaram,
      PinNonce: data.PinNonce.present ? data.PinNonce.value : this.PinNonce,
      PinSandi: data.PinSandi.present ? data.PinSandi.value : this.PinSandi,
    );
  }

  @override
  String toString() {
    return (StringBuffer('BarisStaf(')
          ..write('Uuid: $Uuid, ')
          ..write('Nama: $Nama, ')
          ..write('Pemilik: $Pemilik, ')
          ..write('Izin: $Izin, ')
          ..write('PinDiatur: $PinDiatur, ')
          ..write('PinGaram: $PinGaram, ')
          ..write('PinNonce: $PinNonce, ')
          ..write('PinSandi: $PinSandi')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(Uuid, Nama, Pemilik, Izin, PinDiatur, PinGaram, PinNonce, PinSandi);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is BarisStaf &&
          other.Uuid == this.Uuid &&
          other.Nama == this.Nama &&
          other.Pemilik == this.Pemilik &&
          other.Izin == this.Izin &&
          other.PinDiatur == this.PinDiatur &&
          other.PinGaram == this.PinGaram &&
          other.PinNonce == this.PinNonce &&
          other.PinSandi == this.PinSandi);
}

class StafCompanion extends UpdateCompanion<BarisStaf> {
  final Value<String> Uuid;
  final Value<String> Nama;
  final Value<bool> Pemilik;
  final Value<String> Izin;
  final Value<bool> PinDiatur;
  final Value<String?> PinGaram;
  final Value<String?> PinNonce;
  final Value<String?> PinSandi;
  final Value<int> rowid;
  const StafCompanion({
    this.Uuid = const Value.absent(),
    this.Nama = const Value.absent(),
    this.Pemilik = const Value.absent(),
    this.Izin = const Value.absent(),
    this.PinDiatur = const Value.absent(),
    this.PinGaram = const Value.absent(),
    this.PinNonce = const Value.absent(),
    this.PinSandi = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  StafCompanion.insert({
    required String Uuid,
    required String Nama,
    required bool Pemilik,
    required String Izin,
    required bool PinDiatur,
    this.PinGaram = const Value.absent(),
    this.PinNonce = const Value.absent(),
    this.PinSandi = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : Uuid = Value(Uuid),
       Nama = Value(Nama),
       Pemilik = Value(Pemilik),
       Izin = Value(Izin),
       PinDiatur = Value(PinDiatur);
  static Insertable<BarisStaf> custom({
    Expression<String>? Uuid,
    Expression<String>? Nama,
    Expression<bool>? Pemilik,
    Expression<String>? Izin,
    Expression<bool>? PinDiatur,
    Expression<String>? PinGaram,
    Expression<String>? PinNonce,
    Expression<String>? PinSandi,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (Uuid != null) 'Uuid': Uuid,
      if (Nama != null) 'Nama': Nama,
      if (Pemilik != null) 'Pemilik': Pemilik,
      if (Izin != null) 'Izin': Izin,
      if (PinDiatur != null) 'PinDiatur': PinDiatur,
      if (PinGaram != null) 'PinGaram': PinGaram,
      if (PinNonce != null) 'PinNonce': PinNonce,
      if (PinSandi != null) 'PinSandi': PinSandi,
      if (rowid != null) 'rowid': rowid,
    });
  }

  StafCompanion copyWith({
    Value<String>? Uuid,
    Value<String>? Nama,
    Value<bool>? Pemilik,
    Value<String>? Izin,
    Value<bool>? PinDiatur,
    Value<String?>? PinGaram,
    Value<String?>? PinNonce,
    Value<String?>? PinSandi,
    Value<int>? rowid,
  }) {
    return StafCompanion(
      Uuid: Uuid ?? this.Uuid,
      Nama: Nama ?? this.Nama,
      Pemilik: Pemilik ?? this.Pemilik,
      Izin: Izin ?? this.Izin,
      PinDiatur: PinDiatur ?? this.PinDiatur,
      PinGaram: PinGaram ?? this.PinGaram,
      PinNonce: PinNonce ?? this.PinNonce,
      PinSandi: PinSandi ?? this.PinSandi,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (Uuid.present) {
      map['Uuid'] = Variable<String>(Uuid.value);
    }
    if (Nama.present) {
      map['Nama'] = Variable<String>(Nama.value);
    }
    if (Pemilik.present) {
      map['Pemilik'] = Variable<bool>(Pemilik.value);
    }
    if (Izin.present) {
      map['Izin'] = Variable<String>(Izin.value);
    }
    if (PinDiatur.present) {
      map['PinDiatur'] = Variable<bool>(PinDiatur.value);
    }
    if (PinGaram.present) {
      map['PinGaram'] = Variable<String>(PinGaram.value);
    }
    if (PinNonce.present) {
      map['PinNonce'] = Variable<String>(PinNonce.value);
    }
    if (PinSandi.present) {
      map['PinSandi'] = Variable<String>(PinSandi.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('StafCompanion(')
          ..write('Uuid: $Uuid, ')
          ..write('Nama: $Nama, ')
          ..write('Pemilik: $Pemilik, ')
          ..write('Izin: $Izin, ')
          ..write('PinDiatur: $PinDiatur, ')
          ..write('PinGaram: $PinGaram, ')
          ..write('PinNonce: $PinNonce, ')
          ..write('PinSandi: $PinSandi, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $KategoriKasTable extends KategoriKas with TableInfo<$KategoriKasTable, BarisKategoriKas> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $KategoriKasTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _UuidMeta = const VerificationMeta('Uuid');
  @override
  late final GeneratedColumn<String> Uuid = GeneratedColumn<String>(
    'Uuid',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _NamaMeta = const VerificationMeta('Nama');
  @override
  late final GeneratedColumn<String> Nama = GeneratedColumn<String>(
    'Nama',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _JenisMeta = const VerificationMeta('Jenis');
  @override
  late final GeneratedColumn<String> Jenis = GeneratedColumn<String>(
    'Jenis',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [Uuid, Nama, Jenis];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'KategoriKas';
  @override
  VerificationContext validateIntegrity(Insertable<BarisKategoriKas> instance, {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('Uuid')) {
      context.handle(_UuidMeta, Uuid.isAcceptableOrUnknown(data['Uuid']!, _UuidMeta));
    } else if (isInserting) {
      context.missing(_UuidMeta);
    }
    if (data.containsKey('Nama')) {
      context.handle(_NamaMeta, Nama.isAcceptableOrUnknown(data['Nama']!, _NamaMeta));
    } else if (isInserting) {
      context.missing(_NamaMeta);
    }
    if (data.containsKey('Jenis')) {
      context.handle(_JenisMeta, Jenis.isAcceptableOrUnknown(data['Jenis']!, _JenisMeta));
    } else if (isInserting) {
      context.missing(_JenisMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {Uuid};
  @override
  BarisKategoriKas map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return BarisKategoriKas(
      Uuid: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Uuid'])!,
      Nama: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Nama'])!,
      Jenis: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Jenis'])!,
    );
  }

  @override
  $KategoriKasTable createAlias(String alias) {
    return $KategoriKasTable(attachedDatabase, alias);
  }
}

class BarisKategoriKas extends DataClass implements Insertable<BarisKategoriKas> {
  final String Uuid;
  final String Nama;
  final String Jenis;
  const BarisKategoriKas({required this.Uuid, required this.Nama, required this.Jenis});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['Uuid'] = Variable<String>(Uuid);
    map['Nama'] = Variable<String>(Nama);
    map['Jenis'] = Variable<String>(Jenis);
    return map;
  }

  KategoriKasCompanion toCompanion(bool nullToAbsent) {
    return KategoriKasCompanion(Uuid: Value(Uuid), Nama: Value(Nama), Jenis: Value(Jenis));
  }

  factory BarisKategoriKas.fromJson(Map<String, dynamic> json, {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return BarisKategoriKas(
      Uuid: serializer.fromJson<String>(json['Uuid']),
      Nama: serializer.fromJson<String>(json['Nama']),
      Jenis: serializer.fromJson<String>(json['Jenis']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'Uuid': serializer.toJson<String>(Uuid),
      'Nama': serializer.toJson<String>(Nama),
      'Jenis': serializer.toJson<String>(Jenis),
    };
  }

  BarisKategoriKas copyWith({String? Uuid, String? Nama, String? Jenis}) =>
      BarisKategoriKas(Uuid: Uuid ?? this.Uuid, Nama: Nama ?? this.Nama, Jenis: Jenis ?? this.Jenis);
  BarisKategoriKas copyWithCompanion(KategoriKasCompanion data) {
    return BarisKategoriKas(
      Uuid: data.Uuid.present ? data.Uuid.value : this.Uuid,
      Nama: data.Nama.present ? data.Nama.value : this.Nama,
      Jenis: data.Jenis.present ? data.Jenis.value : this.Jenis,
    );
  }

  @override
  String toString() {
    return (StringBuffer('BarisKategoriKas(')
          ..write('Uuid: $Uuid, ')
          ..write('Nama: $Nama, ')
          ..write('Jenis: $Jenis')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(Uuid, Nama, Jenis);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is BarisKategoriKas && other.Uuid == this.Uuid && other.Nama == this.Nama && other.Jenis == this.Jenis);
}

class KategoriKasCompanion extends UpdateCompanion<BarisKategoriKas> {
  final Value<String> Uuid;
  final Value<String> Nama;
  final Value<String> Jenis;
  final Value<int> rowid;
  const KategoriKasCompanion({
    this.Uuid = const Value.absent(),
    this.Nama = const Value.absent(),
    this.Jenis = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  KategoriKasCompanion.insert({
    required String Uuid,
    required String Nama,
    required String Jenis,
    this.rowid = const Value.absent(),
  }) : Uuid = Value(Uuid),
       Nama = Value(Nama),
       Jenis = Value(Jenis);
  static Insertable<BarisKategoriKas> custom({
    Expression<String>? Uuid,
    Expression<String>? Nama,
    Expression<String>? Jenis,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (Uuid != null) 'Uuid': Uuid,
      if (Nama != null) 'Nama': Nama,
      if (Jenis != null) 'Jenis': Jenis,
      if (rowid != null) 'rowid': rowid,
    });
  }

  KategoriKasCompanion copyWith({Value<String>? Uuid, Value<String>? Nama, Value<String>? Jenis, Value<int>? rowid}) {
    return KategoriKasCompanion(
      Uuid: Uuid ?? this.Uuid,
      Nama: Nama ?? this.Nama,
      Jenis: Jenis ?? this.Jenis,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (Uuid.present) {
      map['Uuid'] = Variable<String>(Uuid.value);
    }
    if (Nama.present) {
      map['Nama'] = Variable<String>(Nama.value);
    }
    if (Jenis.present) {
      map['Jenis'] = Variable<String>(Jenis.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('KategoriKasCompanion(')
          ..write('Uuid: $Uuid, ')
          ..write('Nama: $Nama, ')
          ..write('Jenis: $Jenis, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $ShiftTable extends Shift with TableInfo<$ShiftTable, BarisShift> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $ShiftTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _UuidMeta = const VerificationMeta('Uuid');
  @override
  late final GeneratedColumn<String> Uuid = GeneratedColumn<String>(
    'Uuid',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _DibukaOlehMeta = const VerificationMeta('DibukaOleh');
  @override
  late final GeneratedColumn<String> DibukaOleh = GeneratedColumn<String>(
    'DibukaOleh',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _NamaKasirMeta = const VerificationMeta('NamaKasir');
  @override
  late final GeneratedColumn<String> NamaKasir = GeneratedColumn<String>(
    'NamaKasir',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _DibukaPadaMeta = const VerificationMeta('DibukaPada');
  @override
  late final GeneratedColumn<DateTime> DibukaPada = GeneratedColumn<DateTime>(
    'DibukaPada',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _KasAwalMeta = const VerificationMeta('KasAwal');
  @override
  late final GeneratedColumn<String> KasAwal = GeneratedColumn<String>(
    'KasAwal',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _PecahanKasAwalMeta = const VerificationMeta('PecahanKasAwal');
  @override
  late final GeneratedColumn<String> PecahanKasAwal = GeneratedColumn<String>(
    'PecahanKasAwal',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _BersamaMeta = const VerificationMeta('Bersama');
  @override
  late final GeneratedColumn<bool> Bersama = GeneratedColumn<bool>(
    'Bersama',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: true,
    defaultConstraints: GeneratedColumn.constraintIsAlways('CHECK ("Bersama" IN (0, 1))'),
  );
  static const VerificationMeta _StatusMeta = const VerificationMeta('Status');
  @override
  late final GeneratedColumn<String> Status = GeneratedColumn<String>(
    'Status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    Uuid,
    DibukaOleh,
    NamaKasir,
    DibukaPada,
    KasAwal,
    PecahanKasAwal,
    Bersama,
    Status,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'Shift';
  @override
  VerificationContext validateIntegrity(Insertable<BarisShift> instance, {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('Uuid')) {
      context.handle(_UuidMeta, Uuid.isAcceptableOrUnknown(data['Uuid']!, _UuidMeta));
    } else if (isInserting) {
      context.missing(_UuidMeta);
    }
    if (data.containsKey('DibukaOleh')) {
      context.handle(_DibukaOlehMeta, DibukaOleh.isAcceptableOrUnknown(data['DibukaOleh']!, _DibukaOlehMeta));
    } else if (isInserting) {
      context.missing(_DibukaOlehMeta);
    }
    if (data.containsKey('NamaKasir')) {
      context.handle(_NamaKasirMeta, NamaKasir.isAcceptableOrUnknown(data['NamaKasir']!, _NamaKasirMeta));
    } else if (isInserting) {
      context.missing(_NamaKasirMeta);
    }
    if (data.containsKey('DibukaPada')) {
      context.handle(_DibukaPadaMeta, DibukaPada.isAcceptableOrUnknown(data['DibukaPada']!, _DibukaPadaMeta));
    } else if (isInserting) {
      context.missing(_DibukaPadaMeta);
    }
    if (data.containsKey('KasAwal')) {
      context.handle(_KasAwalMeta, KasAwal.isAcceptableOrUnknown(data['KasAwal']!, _KasAwalMeta));
    } else if (isInserting) {
      context.missing(_KasAwalMeta);
    }
    if (data.containsKey('PecahanKasAwal')) {
      context.handle(
        _PecahanKasAwalMeta,
        PecahanKasAwal.isAcceptableOrUnknown(data['PecahanKasAwal']!, _PecahanKasAwalMeta),
      );
    }
    if (data.containsKey('Bersama')) {
      context.handle(_BersamaMeta, Bersama.isAcceptableOrUnknown(data['Bersama']!, _BersamaMeta));
    } else if (isInserting) {
      context.missing(_BersamaMeta);
    }
    if (data.containsKey('Status')) {
      context.handle(_StatusMeta, Status.isAcceptableOrUnknown(data['Status']!, _StatusMeta));
    } else if (isInserting) {
      context.missing(_StatusMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {Uuid};
  @override
  BarisShift map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return BarisShift(
      Uuid: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Uuid'])!,
      DibukaOleh: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}DibukaOleh'])!,
      NamaKasir: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}NamaKasir'])!,
      DibukaPada: attachedDatabase.typeMapping.read(DriftSqlType.dateTime, data['${effectivePrefix}DibukaPada'])!,
      KasAwal: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}KasAwal'])!,
      PecahanKasAwal: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}PecahanKasAwal']),
      Bersama: attachedDatabase.typeMapping.read(DriftSqlType.bool, data['${effectivePrefix}Bersama'])!,
      Status: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Status'])!,
    );
  }

  @override
  $ShiftTable createAlias(String alias) {
    return $ShiftTable(attachedDatabase, alias);
  }
}

class BarisShift extends DataClass implements Insertable<BarisShift> {
  final String Uuid;
  final String DibukaOleh;
  final String NamaKasir;
  final DateTime DibukaPada;
  final String KasAwal;
  final String? PecahanKasAwal;
  final bool Bersama;
  final String Status;
  const BarisShift({
    required this.Uuid,
    required this.DibukaOleh,
    required this.NamaKasir,
    required this.DibukaPada,
    required this.KasAwal,
    this.PecahanKasAwal,
    required this.Bersama,
    required this.Status,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['Uuid'] = Variable<String>(Uuid);
    map['DibukaOleh'] = Variable<String>(DibukaOleh);
    map['NamaKasir'] = Variable<String>(NamaKasir);
    map['DibukaPada'] = Variable<DateTime>(DibukaPada);
    map['KasAwal'] = Variable<String>(KasAwal);
    if (!nullToAbsent || PecahanKasAwal != null) {
      map['PecahanKasAwal'] = Variable<String>(PecahanKasAwal);
    }
    map['Bersama'] = Variable<bool>(Bersama);
    map['Status'] = Variable<String>(Status);
    return map;
  }

  ShiftCompanion toCompanion(bool nullToAbsent) {
    return ShiftCompanion(
      Uuid: Value(Uuid),
      DibukaOleh: Value(DibukaOleh),
      NamaKasir: Value(NamaKasir),
      DibukaPada: Value(DibukaPada),
      KasAwal: Value(KasAwal),
      PecahanKasAwal: PecahanKasAwal == null && nullToAbsent ? const Value.absent() : Value(PecahanKasAwal),
      Bersama: Value(Bersama),
      Status: Value(Status),
    );
  }

  factory BarisShift.fromJson(Map<String, dynamic> json, {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return BarisShift(
      Uuid: serializer.fromJson<String>(json['Uuid']),
      DibukaOleh: serializer.fromJson<String>(json['DibukaOleh']),
      NamaKasir: serializer.fromJson<String>(json['NamaKasir']),
      DibukaPada: serializer.fromJson<DateTime>(json['DibukaPada']),
      KasAwal: serializer.fromJson<String>(json['KasAwal']),
      PecahanKasAwal: serializer.fromJson<String?>(json['PecahanKasAwal']),
      Bersama: serializer.fromJson<bool>(json['Bersama']),
      Status: serializer.fromJson<String>(json['Status']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'Uuid': serializer.toJson<String>(Uuid),
      'DibukaOleh': serializer.toJson<String>(DibukaOleh),
      'NamaKasir': serializer.toJson<String>(NamaKasir),
      'DibukaPada': serializer.toJson<DateTime>(DibukaPada),
      'KasAwal': serializer.toJson<String>(KasAwal),
      'PecahanKasAwal': serializer.toJson<String?>(PecahanKasAwal),
      'Bersama': serializer.toJson<bool>(Bersama),
      'Status': serializer.toJson<String>(Status),
    };
  }

  BarisShift copyWith({
    String? Uuid,
    String? DibukaOleh,
    String? NamaKasir,
    DateTime? DibukaPada,
    String? KasAwal,
    Value<String?> PecahanKasAwal = const Value.absent(),
    bool? Bersama,
    String? Status,
  }) => BarisShift(
    Uuid: Uuid ?? this.Uuid,
    DibukaOleh: DibukaOleh ?? this.DibukaOleh,
    NamaKasir: NamaKasir ?? this.NamaKasir,
    DibukaPada: DibukaPada ?? this.DibukaPada,
    KasAwal: KasAwal ?? this.KasAwal,
    PecahanKasAwal: PecahanKasAwal.present ? PecahanKasAwal.value : this.PecahanKasAwal,
    Bersama: Bersama ?? this.Bersama,
    Status: Status ?? this.Status,
  );
  BarisShift copyWithCompanion(ShiftCompanion data) {
    return BarisShift(
      Uuid: data.Uuid.present ? data.Uuid.value : this.Uuid,
      DibukaOleh: data.DibukaOleh.present ? data.DibukaOleh.value : this.DibukaOleh,
      NamaKasir: data.NamaKasir.present ? data.NamaKasir.value : this.NamaKasir,
      DibukaPada: data.DibukaPada.present ? data.DibukaPada.value : this.DibukaPada,
      KasAwal: data.KasAwal.present ? data.KasAwal.value : this.KasAwal,
      PecahanKasAwal: data.PecahanKasAwal.present ? data.PecahanKasAwal.value : this.PecahanKasAwal,
      Bersama: data.Bersama.present ? data.Bersama.value : this.Bersama,
      Status: data.Status.present ? data.Status.value : this.Status,
    );
  }

  @override
  String toString() {
    return (StringBuffer('BarisShift(')
          ..write('Uuid: $Uuid, ')
          ..write('DibukaOleh: $DibukaOleh, ')
          ..write('NamaKasir: $NamaKasir, ')
          ..write('DibukaPada: $DibukaPada, ')
          ..write('KasAwal: $KasAwal, ')
          ..write('PecahanKasAwal: $PecahanKasAwal, ')
          ..write('Bersama: $Bersama, ')
          ..write('Status: $Status')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(Uuid, DibukaOleh, NamaKasir, DibukaPada, KasAwal, PecahanKasAwal, Bersama, Status);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is BarisShift &&
          other.Uuid == this.Uuid &&
          other.DibukaOleh == this.DibukaOleh &&
          other.NamaKasir == this.NamaKasir &&
          other.DibukaPada == this.DibukaPada &&
          other.KasAwal == this.KasAwal &&
          other.PecahanKasAwal == this.PecahanKasAwal &&
          other.Bersama == this.Bersama &&
          other.Status == this.Status);
}

class ShiftCompanion extends UpdateCompanion<BarisShift> {
  final Value<String> Uuid;
  final Value<String> DibukaOleh;
  final Value<String> NamaKasir;
  final Value<DateTime> DibukaPada;
  final Value<String> KasAwal;
  final Value<String?> PecahanKasAwal;
  final Value<bool> Bersama;
  final Value<String> Status;
  final Value<int> rowid;
  const ShiftCompanion({
    this.Uuid = const Value.absent(),
    this.DibukaOleh = const Value.absent(),
    this.NamaKasir = const Value.absent(),
    this.DibukaPada = const Value.absent(),
    this.KasAwal = const Value.absent(),
    this.PecahanKasAwal = const Value.absent(),
    this.Bersama = const Value.absent(),
    this.Status = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  ShiftCompanion.insert({
    required String Uuid,
    required String DibukaOleh,
    required String NamaKasir,
    required DateTime DibukaPada,
    required String KasAwal,
    this.PecahanKasAwal = const Value.absent(),
    required bool Bersama,
    required String Status,
    this.rowid = const Value.absent(),
  }) : Uuid = Value(Uuid),
       DibukaOleh = Value(DibukaOleh),
       NamaKasir = Value(NamaKasir),
       DibukaPada = Value(DibukaPada),
       KasAwal = Value(KasAwal),
       Bersama = Value(Bersama),
       Status = Value(Status);
  static Insertable<BarisShift> custom({
    Expression<String>? Uuid,
    Expression<String>? DibukaOleh,
    Expression<String>? NamaKasir,
    Expression<DateTime>? DibukaPada,
    Expression<String>? KasAwal,
    Expression<String>? PecahanKasAwal,
    Expression<bool>? Bersama,
    Expression<String>? Status,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (Uuid != null) 'Uuid': Uuid,
      if (DibukaOleh != null) 'DibukaOleh': DibukaOleh,
      if (NamaKasir != null) 'NamaKasir': NamaKasir,
      if (DibukaPada != null) 'DibukaPada': DibukaPada,
      if (KasAwal != null) 'KasAwal': KasAwal,
      if (PecahanKasAwal != null) 'PecahanKasAwal': PecahanKasAwal,
      if (Bersama != null) 'Bersama': Bersama,
      if (Status != null) 'Status': Status,
      if (rowid != null) 'rowid': rowid,
    });
  }

  ShiftCompanion copyWith({
    Value<String>? Uuid,
    Value<String>? DibukaOleh,
    Value<String>? NamaKasir,
    Value<DateTime>? DibukaPada,
    Value<String>? KasAwal,
    Value<String?>? PecahanKasAwal,
    Value<bool>? Bersama,
    Value<String>? Status,
    Value<int>? rowid,
  }) {
    return ShiftCompanion(
      Uuid: Uuid ?? this.Uuid,
      DibukaOleh: DibukaOleh ?? this.DibukaOleh,
      NamaKasir: NamaKasir ?? this.NamaKasir,
      DibukaPada: DibukaPada ?? this.DibukaPada,
      KasAwal: KasAwal ?? this.KasAwal,
      PecahanKasAwal: PecahanKasAwal ?? this.PecahanKasAwal,
      Bersama: Bersama ?? this.Bersama,
      Status: Status ?? this.Status,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (Uuid.present) {
      map['Uuid'] = Variable<String>(Uuid.value);
    }
    if (DibukaOleh.present) {
      map['DibukaOleh'] = Variable<String>(DibukaOleh.value);
    }
    if (NamaKasir.present) {
      map['NamaKasir'] = Variable<String>(NamaKasir.value);
    }
    if (DibukaPada.present) {
      map['DibukaPada'] = Variable<DateTime>(DibukaPada.value);
    }
    if (KasAwal.present) {
      map['KasAwal'] = Variable<String>(KasAwal.value);
    }
    if (PecahanKasAwal.present) {
      map['PecahanKasAwal'] = Variable<String>(PecahanKasAwal.value);
    }
    if (Bersama.present) {
      map['Bersama'] = Variable<bool>(Bersama.value);
    }
    if (Status.present) {
      map['Status'] = Variable<String>(Status.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('ShiftCompanion(')
          ..write('Uuid: $Uuid, ')
          ..write('DibukaOleh: $DibukaOleh, ')
          ..write('NamaKasir: $NamaKasir, ')
          ..write('DibukaPada: $DibukaPada, ')
          ..write('KasAwal: $KasAwal, ')
          ..write('PecahanKasAwal: $PecahanKasAwal, ')
          ..write('Bersama: $Bersama, ')
          ..write('Status: $Status, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $MutasiKasTable extends MutasiKas with TableInfo<$MutasiKasTable, BarisMutasiKas> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $MutasiKasTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _UuidMeta = const VerificationMeta('Uuid');
  @override
  late final GeneratedColumn<String> Uuid = GeneratedColumn<String>(
    'Uuid',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _UuidShiftMeta = const VerificationMeta('UuidShift');
  @override
  late final GeneratedColumn<String> UuidShift = GeneratedColumn<String>(
    'UuidShift',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    defaultConstraints: GeneratedColumn.constraintIsAlways('REFERENCES Shift (Uuid)'),
  );
  static const VerificationMeta _JenisMeta = const VerificationMeta('Jenis');
  @override
  late final GeneratedColumn<String> Jenis = GeneratedColumn<String>(
    'Jenis',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _UuidKategoriMeta = const VerificationMeta('UuidKategori');
  @override
  late final GeneratedColumn<String> UuidKategori = GeneratedColumn<String>(
    'UuidKategori',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _NamaKategoriMeta = const VerificationMeta('NamaKategori');
  @override
  late final GeneratedColumn<String> NamaKategori = GeneratedColumn<String>(
    'NamaKategori',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _JumlahMeta = const VerificationMeta('Jumlah');
  @override
  late final GeneratedColumn<String> Jumlah = GeneratedColumn<String>(
    'Jumlah',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _CatatanMeta = const VerificationMeta('Catatan');
  @override
  late final GeneratedColumn<String> Catatan = GeneratedColumn<String>(
    'Catatan',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _DicatatOlehMeta = const VerificationMeta('DicatatOleh');
  @override
  late final GeneratedColumn<String> DicatatOleh = GeneratedColumn<String>(
    'DicatatOleh',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _DicatatPadaMeta = const VerificationMeta('DicatatPada');
  @override
  late final GeneratedColumn<DateTime> DicatatPada = GeneratedColumn<DateTime>(
    'DicatatPada',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _DisetujuiOlehMeta = const VerificationMeta('DisetujuiOleh');
  @override
  late final GeneratedColumn<String> DisetujuiOleh = GeneratedColumn<String>(
    'DisetujuiOleh',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    Uuid,
    UuidShift,
    Jenis,
    UuidKategori,
    NamaKategori,
    Jumlah,
    Catatan,
    DicatatOleh,
    DicatatPada,
    DisetujuiOleh,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'MutasiKas';
  @override
  VerificationContext validateIntegrity(Insertable<BarisMutasiKas> instance, {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('Uuid')) {
      context.handle(_UuidMeta, Uuid.isAcceptableOrUnknown(data['Uuid']!, _UuidMeta));
    } else if (isInserting) {
      context.missing(_UuidMeta);
    }
    if (data.containsKey('UuidShift')) {
      context.handle(_UuidShiftMeta, UuidShift.isAcceptableOrUnknown(data['UuidShift']!, _UuidShiftMeta));
    } else if (isInserting) {
      context.missing(_UuidShiftMeta);
    }
    if (data.containsKey('Jenis')) {
      context.handle(_JenisMeta, Jenis.isAcceptableOrUnknown(data['Jenis']!, _JenisMeta));
    } else if (isInserting) {
      context.missing(_JenisMeta);
    }
    if (data.containsKey('UuidKategori')) {
      context.handle(_UuidKategoriMeta, UuidKategori.isAcceptableOrUnknown(data['UuidKategori']!, _UuidKategoriMeta));
    }
    if (data.containsKey('NamaKategori')) {
      context.handle(_NamaKategoriMeta, NamaKategori.isAcceptableOrUnknown(data['NamaKategori']!, _NamaKategoriMeta));
    }
    if (data.containsKey('Jumlah')) {
      context.handle(_JumlahMeta, Jumlah.isAcceptableOrUnknown(data['Jumlah']!, _JumlahMeta));
    } else if (isInserting) {
      context.missing(_JumlahMeta);
    }
    if (data.containsKey('Catatan')) {
      context.handle(_CatatanMeta, Catatan.isAcceptableOrUnknown(data['Catatan']!, _CatatanMeta));
    }
    if (data.containsKey('DicatatOleh')) {
      context.handle(_DicatatOlehMeta, DicatatOleh.isAcceptableOrUnknown(data['DicatatOleh']!, _DicatatOlehMeta));
    } else if (isInserting) {
      context.missing(_DicatatOlehMeta);
    }
    if (data.containsKey('DicatatPada')) {
      context.handle(_DicatatPadaMeta, DicatatPada.isAcceptableOrUnknown(data['DicatatPada']!, _DicatatPadaMeta));
    } else if (isInserting) {
      context.missing(_DicatatPadaMeta);
    }
    if (data.containsKey('DisetujuiOleh')) {
      context.handle(
        _DisetujuiOlehMeta,
        DisetujuiOleh.isAcceptableOrUnknown(data['DisetujuiOleh']!, _DisetujuiOlehMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {Uuid};
  @override
  BarisMutasiKas map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return BarisMutasiKas(
      Uuid: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Uuid'])!,
      UuidShift: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}UuidShift'])!,
      Jenis: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Jenis'])!,
      UuidKategori: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}UuidKategori']),
      NamaKategori: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}NamaKategori']),
      Jumlah: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Jumlah'])!,
      Catatan: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Catatan']),
      DicatatOleh: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}DicatatOleh'])!,
      DicatatPada: attachedDatabase.typeMapping.read(DriftSqlType.dateTime, data['${effectivePrefix}DicatatPada'])!,
      DisetujuiOleh: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}DisetujuiOleh']),
    );
  }

  @override
  $MutasiKasTable createAlias(String alias) {
    return $MutasiKasTable(attachedDatabase, alias);
  }
}

class BarisMutasiKas extends DataClass implements Insertable<BarisMutasiKas> {
  final String Uuid;
  final String UuidShift;
  final String Jenis;
  final String? UuidKategori;
  final String? NamaKategori;
  final String Jumlah;
  final String? Catatan;
  final String DicatatOleh;
  final DateTime DicatatPada;
  final String? DisetujuiOleh;
  const BarisMutasiKas({
    required this.Uuid,
    required this.UuidShift,
    required this.Jenis,
    this.UuidKategori,
    this.NamaKategori,
    required this.Jumlah,
    this.Catatan,
    required this.DicatatOleh,
    required this.DicatatPada,
    this.DisetujuiOleh,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['Uuid'] = Variable<String>(Uuid);
    map['UuidShift'] = Variable<String>(UuidShift);
    map['Jenis'] = Variable<String>(Jenis);
    if (!nullToAbsent || UuidKategori != null) {
      map['UuidKategori'] = Variable<String>(UuidKategori);
    }
    if (!nullToAbsent || NamaKategori != null) {
      map['NamaKategori'] = Variable<String>(NamaKategori);
    }
    map['Jumlah'] = Variable<String>(Jumlah);
    if (!nullToAbsent || Catatan != null) {
      map['Catatan'] = Variable<String>(Catatan);
    }
    map['DicatatOleh'] = Variable<String>(DicatatOleh);
    map['DicatatPada'] = Variable<DateTime>(DicatatPada);
    if (!nullToAbsent || DisetujuiOleh != null) {
      map['DisetujuiOleh'] = Variable<String>(DisetujuiOleh);
    }
    return map;
  }

  MutasiKasCompanion toCompanion(bool nullToAbsent) {
    return MutasiKasCompanion(
      Uuid: Value(Uuid),
      UuidShift: Value(UuidShift),
      Jenis: Value(Jenis),
      UuidKategori: UuidKategori == null && nullToAbsent ? const Value.absent() : Value(UuidKategori),
      NamaKategori: NamaKategori == null && nullToAbsent ? const Value.absent() : Value(NamaKategori),
      Jumlah: Value(Jumlah),
      Catatan: Catatan == null && nullToAbsent ? const Value.absent() : Value(Catatan),
      DicatatOleh: Value(DicatatOleh),
      DicatatPada: Value(DicatatPada),
      DisetujuiOleh: DisetujuiOleh == null && nullToAbsent ? const Value.absent() : Value(DisetujuiOleh),
    );
  }

  factory BarisMutasiKas.fromJson(Map<String, dynamic> json, {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return BarisMutasiKas(
      Uuid: serializer.fromJson<String>(json['Uuid']),
      UuidShift: serializer.fromJson<String>(json['UuidShift']),
      Jenis: serializer.fromJson<String>(json['Jenis']),
      UuidKategori: serializer.fromJson<String?>(json['UuidKategori']),
      NamaKategori: serializer.fromJson<String?>(json['NamaKategori']),
      Jumlah: serializer.fromJson<String>(json['Jumlah']),
      Catatan: serializer.fromJson<String?>(json['Catatan']),
      DicatatOleh: serializer.fromJson<String>(json['DicatatOleh']),
      DicatatPada: serializer.fromJson<DateTime>(json['DicatatPada']),
      DisetujuiOleh: serializer.fromJson<String?>(json['DisetujuiOleh']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'Uuid': serializer.toJson<String>(Uuid),
      'UuidShift': serializer.toJson<String>(UuidShift),
      'Jenis': serializer.toJson<String>(Jenis),
      'UuidKategori': serializer.toJson<String?>(UuidKategori),
      'NamaKategori': serializer.toJson<String?>(NamaKategori),
      'Jumlah': serializer.toJson<String>(Jumlah),
      'Catatan': serializer.toJson<String?>(Catatan),
      'DicatatOleh': serializer.toJson<String>(DicatatOleh),
      'DicatatPada': serializer.toJson<DateTime>(DicatatPada),
      'DisetujuiOleh': serializer.toJson<String?>(DisetujuiOleh),
    };
  }

  BarisMutasiKas copyWith({
    String? Uuid,
    String? UuidShift,
    String? Jenis,
    Value<String?> UuidKategori = const Value.absent(),
    Value<String?> NamaKategori = const Value.absent(),
    String? Jumlah,
    Value<String?> Catatan = const Value.absent(),
    String? DicatatOleh,
    DateTime? DicatatPada,
    Value<String?> DisetujuiOleh = const Value.absent(),
  }) => BarisMutasiKas(
    Uuid: Uuid ?? this.Uuid,
    UuidShift: UuidShift ?? this.UuidShift,
    Jenis: Jenis ?? this.Jenis,
    UuidKategori: UuidKategori.present ? UuidKategori.value : this.UuidKategori,
    NamaKategori: NamaKategori.present ? NamaKategori.value : this.NamaKategori,
    Jumlah: Jumlah ?? this.Jumlah,
    Catatan: Catatan.present ? Catatan.value : this.Catatan,
    DicatatOleh: DicatatOleh ?? this.DicatatOleh,
    DicatatPada: DicatatPada ?? this.DicatatPada,
    DisetujuiOleh: DisetujuiOleh.present ? DisetujuiOleh.value : this.DisetujuiOleh,
  );
  BarisMutasiKas copyWithCompanion(MutasiKasCompanion data) {
    return BarisMutasiKas(
      Uuid: data.Uuid.present ? data.Uuid.value : this.Uuid,
      UuidShift: data.UuidShift.present ? data.UuidShift.value : this.UuidShift,
      Jenis: data.Jenis.present ? data.Jenis.value : this.Jenis,
      UuidKategori: data.UuidKategori.present ? data.UuidKategori.value : this.UuidKategori,
      NamaKategori: data.NamaKategori.present ? data.NamaKategori.value : this.NamaKategori,
      Jumlah: data.Jumlah.present ? data.Jumlah.value : this.Jumlah,
      Catatan: data.Catatan.present ? data.Catatan.value : this.Catatan,
      DicatatOleh: data.DicatatOleh.present ? data.DicatatOleh.value : this.DicatatOleh,
      DicatatPada: data.DicatatPada.present ? data.DicatatPada.value : this.DicatatPada,
      DisetujuiOleh: data.DisetujuiOleh.present ? data.DisetujuiOleh.value : this.DisetujuiOleh,
    );
  }

  @override
  String toString() {
    return (StringBuffer('BarisMutasiKas(')
          ..write('Uuid: $Uuid, ')
          ..write('UuidShift: $UuidShift, ')
          ..write('Jenis: $Jenis, ')
          ..write('UuidKategori: $UuidKategori, ')
          ..write('NamaKategori: $NamaKategori, ')
          ..write('Jumlah: $Jumlah, ')
          ..write('Catatan: $Catatan, ')
          ..write('DicatatOleh: $DicatatOleh, ')
          ..write('DicatatPada: $DicatatPada, ')
          ..write('DisetujuiOleh: $DisetujuiOleh')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    Uuid,
    UuidShift,
    Jenis,
    UuidKategori,
    NamaKategori,
    Jumlah,
    Catatan,
    DicatatOleh,
    DicatatPada,
    DisetujuiOleh,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is BarisMutasiKas &&
          other.Uuid == this.Uuid &&
          other.UuidShift == this.UuidShift &&
          other.Jenis == this.Jenis &&
          other.UuidKategori == this.UuidKategori &&
          other.NamaKategori == this.NamaKategori &&
          other.Jumlah == this.Jumlah &&
          other.Catatan == this.Catatan &&
          other.DicatatOleh == this.DicatatOleh &&
          other.DicatatPada == this.DicatatPada &&
          other.DisetujuiOleh == this.DisetujuiOleh);
}

class MutasiKasCompanion extends UpdateCompanion<BarisMutasiKas> {
  final Value<String> Uuid;
  final Value<String> UuidShift;
  final Value<String> Jenis;
  final Value<String?> UuidKategori;
  final Value<String?> NamaKategori;
  final Value<String> Jumlah;
  final Value<String?> Catatan;
  final Value<String> DicatatOleh;
  final Value<DateTime> DicatatPada;
  final Value<String?> DisetujuiOleh;
  final Value<int> rowid;
  const MutasiKasCompanion({
    this.Uuid = const Value.absent(),
    this.UuidShift = const Value.absent(),
    this.Jenis = const Value.absent(),
    this.UuidKategori = const Value.absent(),
    this.NamaKategori = const Value.absent(),
    this.Jumlah = const Value.absent(),
    this.Catatan = const Value.absent(),
    this.DicatatOleh = const Value.absent(),
    this.DicatatPada = const Value.absent(),
    this.DisetujuiOleh = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  MutasiKasCompanion.insert({
    required String Uuid,
    required String UuidShift,
    required String Jenis,
    this.UuidKategori = const Value.absent(),
    this.NamaKategori = const Value.absent(),
    required String Jumlah,
    this.Catatan = const Value.absent(),
    required String DicatatOleh,
    required DateTime DicatatPada,
    this.DisetujuiOleh = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : Uuid = Value(Uuid),
       UuidShift = Value(UuidShift),
       Jenis = Value(Jenis),
       Jumlah = Value(Jumlah),
       DicatatOleh = Value(DicatatOleh),
       DicatatPada = Value(DicatatPada);
  static Insertable<BarisMutasiKas> custom({
    Expression<String>? Uuid,
    Expression<String>? UuidShift,
    Expression<String>? Jenis,
    Expression<String>? UuidKategori,
    Expression<String>? NamaKategori,
    Expression<String>? Jumlah,
    Expression<String>? Catatan,
    Expression<String>? DicatatOleh,
    Expression<DateTime>? DicatatPada,
    Expression<String>? DisetujuiOleh,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (Uuid != null) 'Uuid': Uuid,
      if (UuidShift != null) 'UuidShift': UuidShift,
      if (Jenis != null) 'Jenis': Jenis,
      if (UuidKategori != null) 'UuidKategori': UuidKategori,
      if (NamaKategori != null) 'NamaKategori': NamaKategori,
      if (Jumlah != null) 'Jumlah': Jumlah,
      if (Catatan != null) 'Catatan': Catatan,
      if (DicatatOleh != null) 'DicatatOleh': DicatatOleh,
      if (DicatatPada != null) 'DicatatPada': DicatatPada,
      if (DisetujuiOleh != null) 'DisetujuiOleh': DisetujuiOleh,
      if (rowid != null) 'rowid': rowid,
    });
  }

  MutasiKasCompanion copyWith({
    Value<String>? Uuid,
    Value<String>? UuidShift,
    Value<String>? Jenis,
    Value<String?>? UuidKategori,
    Value<String?>? NamaKategori,
    Value<String>? Jumlah,
    Value<String?>? Catatan,
    Value<String>? DicatatOleh,
    Value<DateTime>? DicatatPada,
    Value<String?>? DisetujuiOleh,
    Value<int>? rowid,
  }) {
    return MutasiKasCompanion(
      Uuid: Uuid ?? this.Uuid,
      UuidShift: UuidShift ?? this.UuidShift,
      Jenis: Jenis ?? this.Jenis,
      UuidKategori: UuidKategori ?? this.UuidKategori,
      NamaKategori: NamaKategori ?? this.NamaKategori,
      Jumlah: Jumlah ?? this.Jumlah,
      Catatan: Catatan ?? this.Catatan,
      DicatatOleh: DicatatOleh ?? this.DicatatOleh,
      DicatatPada: DicatatPada ?? this.DicatatPada,
      DisetujuiOleh: DisetujuiOleh ?? this.DisetujuiOleh,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (Uuid.present) {
      map['Uuid'] = Variable<String>(Uuid.value);
    }
    if (UuidShift.present) {
      map['UuidShift'] = Variable<String>(UuidShift.value);
    }
    if (Jenis.present) {
      map['Jenis'] = Variable<String>(Jenis.value);
    }
    if (UuidKategori.present) {
      map['UuidKategori'] = Variable<String>(UuidKategori.value);
    }
    if (NamaKategori.present) {
      map['NamaKategori'] = Variable<String>(NamaKategori.value);
    }
    if (Jumlah.present) {
      map['Jumlah'] = Variable<String>(Jumlah.value);
    }
    if (Catatan.present) {
      map['Catatan'] = Variable<String>(Catatan.value);
    }
    if (DicatatOleh.present) {
      map['DicatatOleh'] = Variable<String>(DicatatOleh.value);
    }
    if (DicatatPada.present) {
      map['DicatatPada'] = Variable<DateTime>(DicatatPada.value);
    }
    if (DisetujuiOleh.present) {
      map['DisetujuiOleh'] = Variable<String>(DisetujuiOleh.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('MutasiKasCompanion(')
          ..write('Uuid: $Uuid, ')
          ..write('UuidShift: $UuidShift, ')
          ..write('Jenis: $Jenis, ')
          ..write('UuidKategori: $UuidKategori, ')
          ..write('NamaKategori: $NamaKategori, ')
          ..write('Jumlah: $Jumlah, ')
          ..write('Catatan: $Catatan, ')
          ..write('DicatatOleh: $DicatatOleh, ')
          ..write('DicatatPada: $DicatatPada, ')
          ..write('DisetujuiOleh: $DisetujuiOleh, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $OutboxTable extends Outbox with TableInfo<$OutboxTable, BarisOutbox> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $OutboxTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _IdMeta = const VerificationMeta('Id');
  @override
  late final GeneratedColumn<int> Id = GeneratedColumn<int>(
    'Id',
    aliasedName,
    false,
    hasAutoIncrement: true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways('PRIMARY KEY AUTOINCREMENT'),
  );
  static const VerificationMeta _UuidMeta = const VerificationMeta('Uuid');
  @override
  late final GeneratedColumn<String> Uuid = GeneratedColumn<String>(
    'Uuid',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    defaultConstraints: GeneratedColumn.constraintIsAlways('UNIQUE'),
  );
  static const VerificationMeta _JenisMeta = const VerificationMeta('Jenis');
  @override
  late final GeneratedColumn<String> Jenis = GeneratedColumn<String>(
    'Jenis',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _DataMeta = const VerificationMeta('Data');
  @override
  late final GeneratedColumn<String> Data = GeneratedColumn<String>(
    'Data',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _StatusMeta = const VerificationMeta('Status');
  @override
  late final GeneratedColumn<String> Status = GeneratedColumn<String>(
    'Status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _PercobaanMeta = const VerificationMeta('Percobaan');
  @override
  late final GeneratedColumn<int> Percobaan = GeneratedColumn<int>(
    'Percobaan',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _KodeGalatMeta = const VerificationMeta('KodeGalat');
  @override
  late final GeneratedColumn<String> KodeGalat = GeneratedColumn<String>(
    'KodeGalat',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _PesanGalatMeta = const VerificationMeta('PesanGalat');
  @override
  late final GeneratedColumn<String> PesanGalat = GeneratedColumn<String>(
    'PesanGalat',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _DibuatPadaMeta = const VerificationMeta('DibuatPada');
  @override
  late final GeneratedColumn<DateTime> DibuatPada = GeneratedColumn<DateTime>(
    'DibuatPada',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _BerikutnyaPadaMeta = const VerificationMeta('BerikutnyaPada');
  @override
  late final GeneratedColumn<DateTime> BerikutnyaPada = GeneratedColumn<DateTime>(
    'BerikutnyaPada',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    Id,
    Uuid,
    Jenis,
    Data,
    Status,
    Percobaan,
    KodeGalat,
    PesanGalat,
    DibuatPada,
    BerikutnyaPada,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'Outbox';
  @override
  VerificationContext validateIntegrity(Insertable<BarisOutbox> instance, {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('Id')) {
      context.handle(_IdMeta, Id.isAcceptableOrUnknown(data['Id']!, _IdMeta));
    }
    if (data.containsKey('Uuid')) {
      context.handle(_UuidMeta, Uuid.isAcceptableOrUnknown(data['Uuid']!, _UuidMeta));
    } else if (isInserting) {
      context.missing(_UuidMeta);
    }
    if (data.containsKey('Jenis')) {
      context.handle(_JenisMeta, Jenis.isAcceptableOrUnknown(data['Jenis']!, _JenisMeta));
    } else if (isInserting) {
      context.missing(_JenisMeta);
    }
    if (data.containsKey('Data')) {
      context.handle(_DataMeta, Data.isAcceptableOrUnknown(data['Data']!, _DataMeta));
    } else if (isInserting) {
      context.missing(_DataMeta);
    }
    if (data.containsKey('Status')) {
      context.handle(_StatusMeta, Status.isAcceptableOrUnknown(data['Status']!, _StatusMeta));
    } else if (isInserting) {
      context.missing(_StatusMeta);
    }
    if (data.containsKey('Percobaan')) {
      context.handle(_PercobaanMeta, Percobaan.isAcceptableOrUnknown(data['Percobaan']!, _PercobaanMeta));
    }
    if (data.containsKey('KodeGalat')) {
      context.handle(_KodeGalatMeta, KodeGalat.isAcceptableOrUnknown(data['KodeGalat']!, _KodeGalatMeta));
    }
    if (data.containsKey('PesanGalat')) {
      context.handle(_PesanGalatMeta, PesanGalat.isAcceptableOrUnknown(data['PesanGalat']!, _PesanGalatMeta));
    }
    if (data.containsKey('DibuatPada')) {
      context.handle(_DibuatPadaMeta, DibuatPada.isAcceptableOrUnknown(data['DibuatPada']!, _DibuatPadaMeta));
    } else if (isInserting) {
      context.missing(_DibuatPadaMeta);
    }
    if (data.containsKey('BerikutnyaPada')) {
      context.handle(
        _BerikutnyaPadaMeta,
        BerikutnyaPada.isAcceptableOrUnknown(data['BerikutnyaPada']!, _BerikutnyaPadaMeta),
      );
    } else if (isInserting) {
      context.missing(_BerikutnyaPadaMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {Id};
  @override
  BarisOutbox map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return BarisOutbox(
      Id: attachedDatabase.typeMapping.read(DriftSqlType.int, data['${effectivePrefix}Id'])!,
      Uuid: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Uuid'])!,
      Jenis: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Jenis'])!,
      Data: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Data'])!,
      Status: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}Status'])!,
      Percobaan: attachedDatabase.typeMapping.read(DriftSqlType.int, data['${effectivePrefix}Percobaan'])!,
      KodeGalat: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}KodeGalat']),
      PesanGalat: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}PesanGalat']),
      DibuatPada: attachedDatabase.typeMapping.read(DriftSqlType.dateTime, data['${effectivePrefix}DibuatPada'])!,
      BerikutnyaPada: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}BerikutnyaPada'],
      )!,
    );
  }

  @override
  $OutboxTable createAlias(String alias) {
    return $OutboxTable(attachedDatabase, alias);
  }
}

class BarisOutbox extends DataClass implements Insertable<BarisOutbox> {
  final int Id;
  final String Uuid;
  final String Jenis;
  final String Data;
  final String Status;
  final int Percobaan;
  final String? KodeGalat;
  final String? PesanGalat;
  final DateTime DibuatPada;
  final DateTime BerikutnyaPada;
  const BarisOutbox({
    required this.Id,
    required this.Uuid,
    required this.Jenis,
    required this.Data,
    required this.Status,
    required this.Percobaan,
    this.KodeGalat,
    this.PesanGalat,
    required this.DibuatPada,
    required this.BerikutnyaPada,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['Id'] = Variable<int>(Id);
    map['Uuid'] = Variable<String>(Uuid);
    map['Jenis'] = Variable<String>(Jenis);
    map['Data'] = Variable<String>(Data);
    map['Status'] = Variable<String>(Status);
    map['Percobaan'] = Variable<int>(Percobaan);
    if (!nullToAbsent || KodeGalat != null) {
      map['KodeGalat'] = Variable<String>(KodeGalat);
    }
    if (!nullToAbsent || PesanGalat != null) {
      map['PesanGalat'] = Variable<String>(PesanGalat);
    }
    map['DibuatPada'] = Variable<DateTime>(DibuatPada);
    map['BerikutnyaPada'] = Variable<DateTime>(BerikutnyaPada);
    return map;
  }

  OutboxCompanion toCompanion(bool nullToAbsent) {
    return OutboxCompanion(
      Id: Value(Id),
      Uuid: Value(Uuid),
      Jenis: Value(Jenis),
      Data: Value(Data),
      Status: Value(Status),
      Percobaan: Value(Percobaan),
      KodeGalat: KodeGalat == null && nullToAbsent ? const Value.absent() : Value(KodeGalat),
      PesanGalat: PesanGalat == null && nullToAbsent ? const Value.absent() : Value(PesanGalat),
      DibuatPada: Value(DibuatPada),
      BerikutnyaPada: Value(BerikutnyaPada),
    );
  }

  factory BarisOutbox.fromJson(Map<String, dynamic> json, {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return BarisOutbox(
      Id: serializer.fromJson<int>(json['Id']),
      Uuid: serializer.fromJson<String>(json['Uuid']),
      Jenis: serializer.fromJson<String>(json['Jenis']),
      Data: serializer.fromJson<String>(json['Data']),
      Status: serializer.fromJson<String>(json['Status']),
      Percobaan: serializer.fromJson<int>(json['Percobaan']),
      KodeGalat: serializer.fromJson<String?>(json['KodeGalat']),
      PesanGalat: serializer.fromJson<String?>(json['PesanGalat']),
      DibuatPada: serializer.fromJson<DateTime>(json['DibuatPada']),
      BerikutnyaPada: serializer.fromJson<DateTime>(json['BerikutnyaPada']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'Id': serializer.toJson<int>(Id),
      'Uuid': serializer.toJson<String>(Uuid),
      'Jenis': serializer.toJson<String>(Jenis),
      'Data': serializer.toJson<String>(Data),
      'Status': serializer.toJson<String>(Status),
      'Percobaan': serializer.toJson<int>(Percobaan),
      'KodeGalat': serializer.toJson<String?>(KodeGalat),
      'PesanGalat': serializer.toJson<String?>(PesanGalat),
      'DibuatPada': serializer.toJson<DateTime>(DibuatPada),
      'BerikutnyaPada': serializer.toJson<DateTime>(BerikutnyaPada),
    };
  }

  BarisOutbox copyWith({
    int? Id,
    String? Uuid,
    String? Jenis,
    String? Data,
    String? Status,
    int? Percobaan,
    Value<String?> KodeGalat = const Value.absent(),
    Value<String?> PesanGalat = const Value.absent(),
    DateTime? DibuatPada,
    DateTime? BerikutnyaPada,
  }) => BarisOutbox(
    Id: Id ?? this.Id,
    Uuid: Uuid ?? this.Uuid,
    Jenis: Jenis ?? this.Jenis,
    Data: Data ?? this.Data,
    Status: Status ?? this.Status,
    Percobaan: Percobaan ?? this.Percobaan,
    KodeGalat: KodeGalat.present ? KodeGalat.value : this.KodeGalat,
    PesanGalat: PesanGalat.present ? PesanGalat.value : this.PesanGalat,
    DibuatPada: DibuatPada ?? this.DibuatPada,
    BerikutnyaPada: BerikutnyaPada ?? this.BerikutnyaPada,
  );
  BarisOutbox copyWithCompanion(OutboxCompanion data) {
    return BarisOutbox(
      Id: data.Id.present ? data.Id.value : this.Id,
      Uuid: data.Uuid.present ? data.Uuid.value : this.Uuid,
      Jenis: data.Jenis.present ? data.Jenis.value : this.Jenis,
      Data: data.Data.present ? data.Data.value : this.Data,
      Status: data.Status.present ? data.Status.value : this.Status,
      Percobaan: data.Percobaan.present ? data.Percobaan.value : this.Percobaan,
      KodeGalat: data.KodeGalat.present ? data.KodeGalat.value : this.KodeGalat,
      PesanGalat: data.PesanGalat.present ? data.PesanGalat.value : this.PesanGalat,
      DibuatPada: data.DibuatPada.present ? data.DibuatPada.value : this.DibuatPada,
      BerikutnyaPada: data.BerikutnyaPada.present ? data.BerikutnyaPada.value : this.BerikutnyaPada,
    );
  }

  @override
  String toString() {
    return (StringBuffer('BarisOutbox(')
          ..write('Id: $Id, ')
          ..write('Uuid: $Uuid, ')
          ..write('Jenis: $Jenis, ')
          ..write('Data: $Data, ')
          ..write('Status: $Status, ')
          ..write('Percobaan: $Percobaan, ')
          ..write('KodeGalat: $KodeGalat, ')
          ..write('PesanGalat: $PesanGalat, ')
          ..write('DibuatPada: $DibuatPada, ')
          ..write('BerikutnyaPada: $BerikutnyaPada')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode =>
      Object.hash(Id, Uuid, Jenis, Data, Status, Percobaan, KodeGalat, PesanGalat, DibuatPada, BerikutnyaPada);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is BarisOutbox &&
          other.Id == this.Id &&
          other.Uuid == this.Uuid &&
          other.Jenis == this.Jenis &&
          other.Data == this.Data &&
          other.Status == this.Status &&
          other.Percobaan == this.Percobaan &&
          other.KodeGalat == this.KodeGalat &&
          other.PesanGalat == this.PesanGalat &&
          other.DibuatPada == this.DibuatPada &&
          other.BerikutnyaPada == this.BerikutnyaPada);
}

class OutboxCompanion extends UpdateCompanion<BarisOutbox> {
  final Value<int> Id;
  final Value<String> Uuid;
  final Value<String> Jenis;
  final Value<String> Data;
  final Value<String> Status;
  final Value<int> Percobaan;
  final Value<String?> KodeGalat;
  final Value<String?> PesanGalat;
  final Value<DateTime> DibuatPada;
  final Value<DateTime> BerikutnyaPada;
  const OutboxCompanion({
    this.Id = const Value.absent(),
    this.Uuid = const Value.absent(),
    this.Jenis = const Value.absent(),
    this.Data = const Value.absent(),
    this.Status = const Value.absent(),
    this.Percobaan = const Value.absent(),
    this.KodeGalat = const Value.absent(),
    this.PesanGalat = const Value.absent(),
    this.DibuatPada = const Value.absent(),
    this.BerikutnyaPada = const Value.absent(),
  });
  OutboxCompanion.insert({
    this.Id = const Value.absent(),
    required String Uuid,
    required String Jenis,
    required String Data,
    required String Status,
    this.Percobaan = const Value.absent(),
    this.KodeGalat = const Value.absent(),
    this.PesanGalat = const Value.absent(),
    required DateTime DibuatPada,
    required DateTime BerikutnyaPada,
  }) : Uuid = Value(Uuid),
       Jenis = Value(Jenis),
       Data = Value(Data),
       Status = Value(Status),
       DibuatPada = Value(DibuatPada),
       BerikutnyaPada = Value(BerikutnyaPada);
  static Insertable<BarisOutbox> custom({
    Expression<int>? Id,
    Expression<String>? Uuid,
    Expression<String>? Jenis,
    Expression<String>? Data,
    Expression<String>? Status,
    Expression<int>? Percobaan,
    Expression<String>? KodeGalat,
    Expression<String>? PesanGalat,
    Expression<DateTime>? DibuatPada,
    Expression<DateTime>? BerikutnyaPada,
  }) {
    return RawValuesInsertable({
      if (Id != null) 'Id': Id,
      if (Uuid != null) 'Uuid': Uuid,
      if (Jenis != null) 'Jenis': Jenis,
      if (Data != null) 'Data': Data,
      if (Status != null) 'Status': Status,
      if (Percobaan != null) 'Percobaan': Percobaan,
      if (KodeGalat != null) 'KodeGalat': KodeGalat,
      if (PesanGalat != null) 'PesanGalat': PesanGalat,
      if (DibuatPada != null) 'DibuatPada': DibuatPada,
      if (BerikutnyaPada != null) 'BerikutnyaPada': BerikutnyaPada,
    });
  }

  OutboxCompanion copyWith({
    Value<int>? Id,
    Value<String>? Uuid,
    Value<String>? Jenis,
    Value<String>? Data,
    Value<String>? Status,
    Value<int>? Percobaan,
    Value<String?>? KodeGalat,
    Value<String?>? PesanGalat,
    Value<DateTime>? DibuatPada,
    Value<DateTime>? BerikutnyaPada,
  }) {
    return OutboxCompanion(
      Id: Id ?? this.Id,
      Uuid: Uuid ?? this.Uuid,
      Jenis: Jenis ?? this.Jenis,
      Data: Data ?? this.Data,
      Status: Status ?? this.Status,
      Percobaan: Percobaan ?? this.Percobaan,
      KodeGalat: KodeGalat ?? this.KodeGalat,
      PesanGalat: PesanGalat ?? this.PesanGalat,
      DibuatPada: DibuatPada ?? this.DibuatPada,
      BerikutnyaPada: BerikutnyaPada ?? this.BerikutnyaPada,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (Id.present) {
      map['Id'] = Variable<int>(Id.value);
    }
    if (Uuid.present) {
      map['Uuid'] = Variable<String>(Uuid.value);
    }
    if (Jenis.present) {
      map['Jenis'] = Variable<String>(Jenis.value);
    }
    if (Data.present) {
      map['Data'] = Variable<String>(Data.value);
    }
    if (Status.present) {
      map['Status'] = Variable<String>(Status.value);
    }
    if (Percobaan.present) {
      map['Percobaan'] = Variable<int>(Percobaan.value);
    }
    if (KodeGalat.present) {
      map['KodeGalat'] = Variable<String>(KodeGalat.value);
    }
    if (PesanGalat.present) {
      map['PesanGalat'] = Variable<String>(PesanGalat.value);
    }
    if (DibuatPada.present) {
      map['DibuatPada'] = Variable<DateTime>(DibuatPada.value);
    }
    if (BerikutnyaPada.present) {
      map['BerikutnyaPada'] = Variable<DateTime>(BerikutnyaPada.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('OutboxCompanion(')
          ..write('Id: $Id, ')
          ..write('Uuid: $Uuid, ')
          ..write('Jenis: $Jenis, ')
          ..write('Data: $Data, ')
          ..write('Status: $Status, ')
          ..write('Percobaan: $Percobaan, ')
          ..write('KodeGalat: $KodeGalat, ')
          ..write('PesanGalat: $PesanGalat, ')
          ..write('DibuatPada: $DibuatPada, ')
          ..write('BerikutnyaPada: $BerikutnyaPada')
          ..write(')'))
        .toString();
  }
}

class $PercobaanPinTable extends PercobaanPin with TableInfo<$PercobaanPinTable, BarisPercobaanPin> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $PercobaanPinTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _UuidPenggunaMeta = const VerificationMeta('UuidPengguna');
  @override
  late final GeneratedColumn<String> UuidPengguna = GeneratedColumn<String>(
    'UuidPengguna',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _JumlahGagalMeta = const VerificationMeta('JumlahGagal');
  @override
  late final GeneratedColumn<int> JumlahGagal = GeneratedColumn<int>(
    'JumlahGagal',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _TerkunciSampaiMeta = const VerificationMeta('TerkunciSampai');
  @override
  late final GeneratedColumn<DateTime> TerkunciSampai = GeneratedColumn<DateTime>(
    'TerkunciSampai',
    aliasedName,
    true,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [UuidPengguna, JumlahGagal, TerkunciSampai];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'PercobaanPin';
  @override
  VerificationContext validateIntegrity(Insertable<BarisPercobaanPin> instance, {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('UuidPengguna')) {
      context.handle(_UuidPenggunaMeta, UuidPengguna.isAcceptableOrUnknown(data['UuidPengguna']!, _UuidPenggunaMeta));
    } else if (isInserting) {
      context.missing(_UuidPenggunaMeta);
    }
    if (data.containsKey('JumlahGagal')) {
      context.handle(_JumlahGagalMeta, JumlahGagal.isAcceptableOrUnknown(data['JumlahGagal']!, _JumlahGagalMeta));
    } else if (isInserting) {
      context.missing(_JumlahGagalMeta);
    }
    if (data.containsKey('TerkunciSampai')) {
      context.handle(
        _TerkunciSampaiMeta,
        TerkunciSampai.isAcceptableOrUnknown(data['TerkunciSampai']!, _TerkunciSampaiMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {UuidPengguna};
  @override
  BarisPercobaanPin map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return BarisPercobaanPin(
      UuidPengguna: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}UuidPengguna'])!,
      JumlahGagal: attachedDatabase.typeMapping.read(DriftSqlType.int, data['${effectivePrefix}JumlahGagal'])!,
      TerkunciSampai: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}TerkunciSampai'],
      ),
    );
  }

  @override
  $PercobaanPinTable createAlias(String alias) {
    return $PercobaanPinTable(attachedDatabase, alias);
  }
}

class BarisPercobaanPin extends DataClass implements Insertable<BarisPercobaanPin> {
  final String UuidPengguna;
  final int JumlahGagal;
  final DateTime? TerkunciSampai;
  const BarisPercobaanPin({required this.UuidPengguna, required this.JumlahGagal, this.TerkunciSampai});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['UuidPengguna'] = Variable<String>(UuidPengguna);
    map['JumlahGagal'] = Variable<int>(JumlahGagal);
    if (!nullToAbsent || TerkunciSampai != null) {
      map['TerkunciSampai'] = Variable<DateTime>(TerkunciSampai);
    }
    return map;
  }

  PercobaanPinCompanion toCompanion(bool nullToAbsent) {
    return PercobaanPinCompanion(
      UuidPengguna: Value(UuidPengguna),
      JumlahGagal: Value(JumlahGagal),
      TerkunciSampai: TerkunciSampai == null && nullToAbsent ? const Value.absent() : Value(TerkunciSampai),
    );
  }

  factory BarisPercobaanPin.fromJson(Map<String, dynamic> json, {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return BarisPercobaanPin(
      UuidPengguna: serializer.fromJson<String>(json['UuidPengguna']),
      JumlahGagal: serializer.fromJson<int>(json['JumlahGagal']),
      TerkunciSampai: serializer.fromJson<DateTime?>(json['TerkunciSampai']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'UuidPengguna': serializer.toJson<String>(UuidPengguna),
      'JumlahGagal': serializer.toJson<int>(JumlahGagal),
      'TerkunciSampai': serializer.toJson<DateTime?>(TerkunciSampai),
    };
  }

  BarisPercobaanPin copyWith({
    String? UuidPengguna,
    int? JumlahGagal,
    Value<DateTime?> TerkunciSampai = const Value.absent(),
  }) => BarisPercobaanPin(
    UuidPengguna: UuidPengguna ?? this.UuidPengguna,
    JumlahGagal: JumlahGagal ?? this.JumlahGagal,
    TerkunciSampai: TerkunciSampai.present ? TerkunciSampai.value : this.TerkunciSampai,
  );
  BarisPercobaanPin copyWithCompanion(PercobaanPinCompanion data) {
    return BarisPercobaanPin(
      UuidPengguna: data.UuidPengguna.present ? data.UuidPengguna.value : this.UuidPengguna,
      JumlahGagal: data.JumlahGagal.present ? data.JumlahGagal.value : this.JumlahGagal,
      TerkunciSampai: data.TerkunciSampai.present ? data.TerkunciSampai.value : this.TerkunciSampai,
    );
  }

  @override
  String toString() {
    return (StringBuffer('BarisPercobaanPin(')
          ..write('UuidPengguna: $UuidPengguna, ')
          ..write('JumlahGagal: $JumlahGagal, ')
          ..write('TerkunciSampai: $TerkunciSampai')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(UuidPengguna, JumlahGagal, TerkunciSampai);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is BarisPercobaanPin &&
          other.UuidPengguna == this.UuidPengguna &&
          other.JumlahGagal == this.JumlahGagal &&
          other.TerkunciSampai == this.TerkunciSampai);
}

class PercobaanPinCompanion extends UpdateCompanion<BarisPercobaanPin> {
  final Value<String> UuidPengguna;
  final Value<int> JumlahGagal;
  final Value<DateTime?> TerkunciSampai;
  final Value<int> rowid;
  const PercobaanPinCompanion({
    this.UuidPengguna = const Value.absent(),
    this.JumlahGagal = const Value.absent(),
    this.TerkunciSampai = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  PercobaanPinCompanion.insert({
    required String UuidPengguna,
    required int JumlahGagal,
    this.TerkunciSampai = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : UuidPengguna = Value(UuidPengguna),
       JumlahGagal = Value(JumlahGagal);
  static Insertable<BarisPercobaanPin> custom({
    Expression<String>? UuidPengguna,
    Expression<int>? JumlahGagal,
    Expression<DateTime>? TerkunciSampai,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (UuidPengguna != null) 'UuidPengguna': UuidPengguna,
      if (JumlahGagal != null) 'JumlahGagal': JumlahGagal,
      if (TerkunciSampai != null) 'TerkunciSampai': TerkunciSampai,
      if (rowid != null) 'rowid': rowid,
    });
  }

  PercobaanPinCompanion copyWith({
    Value<String>? UuidPengguna,
    Value<int>? JumlahGagal,
    Value<DateTime?>? TerkunciSampai,
    Value<int>? rowid,
  }) {
    return PercobaanPinCompanion(
      UuidPengguna: UuidPengguna ?? this.UuidPengguna,
      JumlahGagal: JumlahGagal ?? this.JumlahGagal,
      TerkunciSampai: TerkunciSampai ?? this.TerkunciSampai,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (UuidPengguna.present) {
      map['UuidPengguna'] = Variable<String>(UuidPengguna.value);
    }
    if (JumlahGagal.present) {
      map['JumlahGagal'] = Variable<int>(JumlahGagal.value);
    }
    if (TerkunciSampai.present) {
      map['TerkunciSampai'] = Variable<DateTime>(TerkunciSampai.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('PercobaanPinCompanion(')
          ..write('UuidPengguna: $UuidPengguna, ')
          ..write('JumlahGagal: $JumlahGagal, ')
          ..write('TerkunciSampai: $TerkunciSampai, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

abstract class _$BasisDataKasir extends GeneratedDatabase {
  _$BasisDataKasir(QueryExecutor e) : super(e);
  $BasisDataKasirManager get managers => $BasisDataKasirManager(this);
  late final $PengaturanTable pengaturan = $PengaturanTable(this);
  late final $StafTable staf = $StafTable(this);
  late final $KategoriKasTable kategoriKas = $KategoriKasTable(this);
  late final $ShiftTable shift = $ShiftTable(this);
  late final $MutasiKasTable mutasiKas = $MutasiKasTable(this);
  late final $OutboxTable outbox = $OutboxTable(this);
  late final $PercobaanPinTable percobaanPin = $PercobaanPinTable(this);
  @override
  Iterable<TableInfo<Table, Object?>> get allTables => allSchemaEntities.whereType<TableInfo<Table, Object?>>();
  @override
  List<DatabaseSchemaEntity> get allSchemaEntities => [
    pengaturan,
    staf,
    kategoriKas,
    shift,
    mutasiKas,
    outbox,
    percobaanPin,
  ];
  @override
  DriftDatabaseOptions get options => const DriftDatabaseOptions(storeDateTimeAsText: true);
}

typedef $$PengaturanTableCreateCompanionBuilder = PengaturanCompanion Function({
  required String Kunci,
  required String Nilai,
  Value<int> rowid,
});
typedef $$PengaturanTableUpdateCompanionBuilder = PengaturanCompanion Function({
  Value<String> Kunci,
  Value<String> Nilai,
  Value<int> rowid,
});

class $$PengaturanTableFilterComposer extends Composer<_$BasisDataKasir, $PengaturanTable> {
  $$PengaturanTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get Kunci =>
      $composableBuilder(column: $table.Kunci, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get Nilai =>
      $composableBuilder(column: $table.Nilai, builder: (column) => ColumnFilters(column));
}

class $$PengaturanTableOrderingComposer extends Composer<_$BasisDataKasir, $PengaturanTable> {
  $$PengaturanTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get Kunci =>
      $composableBuilder(column: $table.Kunci, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get Nilai =>
      $composableBuilder(column: $table.Nilai, builder: (column) => ColumnOrderings(column));
}

class $$PengaturanTableAnnotationComposer extends Composer<_$BasisDataKasir, $PengaturanTable> {
  $$PengaturanTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get Kunci => $composableBuilder(column: $table.Kunci, builder: (column) => column);

  GeneratedColumn<String> get Nilai => $composableBuilder(column: $table.Nilai, builder: (column) => column);
}

class $$PengaturanTableTableManager
    extends
        RootTableManager<
          _$BasisDataKasir,
          $PengaturanTable,
          BarisPengaturan,
          $$PengaturanTableFilterComposer,
          $$PengaturanTableOrderingComposer,
          $$PengaturanTableAnnotationComposer,
          $$PengaturanTableCreateCompanionBuilder,
          $$PengaturanTableUpdateCompanionBuilder,
          (BarisPengaturan, BaseReferences<_$BasisDataKasir, $PengaturanTable, BarisPengaturan>),
          BarisPengaturan,
          PrefetchHooks Function()
        > {
  $$PengaturanTableTableManager(_$BasisDataKasir db, $PengaturanTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () => $$PengaturanTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () => $$PengaturanTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () => $$PengaturanTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback: ({
            Value<String> Kunci = const Value.absent(),
            Value<String> Nilai = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) => PengaturanCompanion(Kunci: Kunci, Nilai: Nilai, rowid: rowid),
          createCompanionCallback: ({
            required String Kunci,
            required String Nilai,
            Value<int> rowid = const Value.absent(),
          }) => PengaturanCompanion.insert(Kunci: Kunci, Nilai: Nilai, rowid: rowid),
          withReferenceMapper: (p0) => p0
              .map(
                (e) => (
                  e.readTable<$PengaturanTable, BarisPengaturan>(table),
                  BaseReferences<_$BasisDataKasir, $PengaturanTable, BarisPengaturan>(db, table, e),
                ),
              )
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$PengaturanTableProcessedTableManager =
    ProcessedTableManager<
      _$BasisDataKasir,
      $PengaturanTable,
      BarisPengaturan,
      $$PengaturanTableFilterComposer,
      $$PengaturanTableOrderingComposer,
      $$PengaturanTableAnnotationComposer,
      $$PengaturanTableCreateCompanionBuilder,
      $$PengaturanTableUpdateCompanionBuilder,
      (BarisPengaturan, BaseReferences<_$BasisDataKasir, $PengaturanTable, BarisPengaturan>),
      BarisPengaturan,
      PrefetchHooks Function()
    >;
typedef $$StafTableCreateCompanionBuilder = StafCompanion Function({
  required String Uuid,
  required String Nama,
  required bool Pemilik,
  required String Izin,
  required bool PinDiatur,
  Value<String?> PinGaram,
  Value<String?> PinNonce,
  Value<String?> PinSandi,
  Value<int> rowid,
});
typedef $$StafTableUpdateCompanionBuilder = StafCompanion Function({
  Value<String> Uuid,
  Value<String> Nama,
  Value<bool> Pemilik,
  Value<String> Izin,
  Value<bool> PinDiatur,
  Value<String?> PinGaram,
  Value<String?> PinNonce,
  Value<String?> PinSandi,
  Value<int> rowid,
});

class $$StafTableFilterComposer extends Composer<_$BasisDataKasir, $StafTable> {
  $$StafTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get Uuid => $composableBuilder(column: $table.Uuid, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get Nama => $composableBuilder(column: $table.Nama, builder: (column) => ColumnFilters(column));

  ColumnFilters<bool> get Pemilik =>
      $composableBuilder(column: $table.Pemilik, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get Izin => $composableBuilder(column: $table.Izin, builder: (column) => ColumnFilters(column));

  ColumnFilters<bool> get PinDiatur =>
      $composableBuilder(column: $table.PinDiatur, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get PinGaram =>
      $composableBuilder(column: $table.PinGaram, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get PinNonce =>
      $composableBuilder(column: $table.PinNonce, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get PinSandi =>
      $composableBuilder(column: $table.PinSandi, builder: (column) => ColumnFilters(column));
}

class $$StafTableOrderingComposer extends Composer<_$BasisDataKasir, $StafTable> {
  $$StafTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get Uuid =>
      $composableBuilder(column: $table.Uuid, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get Nama =>
      $composableBuilder(column: $table.Nama, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<bool> get Pemilik =>
      $composableBuilder(column: $table.Pemilik, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get Izin =>
      $composableBuilder(column: $table.Izin, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<bool> get PinDiatur =>
      $composableBuilder(column: $table.PinDiatur, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get PinGaram =>
      $composableBuilder(column: $table.PinGaram, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get PinNonce =>
      $composableBuilder(column: $table.PinNonce, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get PinSandi =>
      $composableBuilder(column: $table.PinSandi, builder: (column) => ColumnOrderings(column));
}

class $$StafTableAnnotationComposer extends Composer<_$BasisDataKasir, $StafTable> {
  $$StafTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get Uuid => $composableBuilder(column: $table.Uuid, builder: (column) => column);

  GeneratedColumn<String> get Nama => $composableBuilder(column: $table.Nama, builder: (column) => column);

  GeneratedColumn<bool> get Pemilik => $composableBuilder(column: $table.Pemilik, builder: (column) => column);

  GeneratedColumn<String> get Izin => $composableBuilder(column: $table.Izin, builder: (column) => column);

  GeneratedColumn<bool> get PinDiatur => $composableBuilder(column: $table.PinDiatur, builder: (column) => column);

  GeneratedColumn<String> get PinGaram => $composableBuilder(column: $table.PinGaram, builder: (column) => column);

  GeneratedColumn<String> get PinNonce => $composableBuilder(column: $table.PinNonce, builder: (column) => column);

  GeneratedColumn<String> get PinSandi => $composableBuilder(column: $table.PinSandi, builder: (column) => column);
}

class $$StafTableTableManager
    extends
        RootTableManager<
          _$BasisDataKasir,
          $StafTable,
          BarisStaf,
          $$StafTableFilterComposer,
          $$StafTableOrderingComposer,
          $$StafTableAnnotationComposer,
          $$StafTableCreateCompanionBuilder,
          $$StafTableUpdateCompanionBuilder,
          (BarisStaf, BaseReferences<_$BasisDataKasir, $StafTable, BarisStaf>),
          BarisStaf,
          PrefetchHooks Function()
        > {
  $$StafTableTableManager(_$BasisDataKasir db, $StafTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () => $$StafTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () => $$StafTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () => $$StafTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> Uuid = const Value.absent(),
                Value<String> Nama = const Value.absent(),
                Value<bool> Pemilik = const Value.absent(),
                Value<String> Izin = const Value.absent(),
                Value<bool> PinDiatur = const Value.absent(),
                Value<String?> PinGaram = const Value.absent(),
                Value<String?> PinNonce = const Value.absent(),
                Value<String?> PinSandi = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => StafCompanion(
                Uuid: Uuid,
                Nama: Nama,
                Pemilik: Pemilik,
                Izin: Izin,
                PinDiatur: PinDiatur,
                PinGaram: PinGaram,
                PinNonce: PinNonce,
                PinSandi: PinSandi,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String Uuid,
                required String Nama,
                required bool Pemilik,
                required String Izin,
                required bool PinDiatur,
                Value<String?> PinGaram = const Value.absent(),
                Value<String?> PinNonce = const Value.absent(),
                Value<String?> PinSandi = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => StafCompanion.insert(
                Uuid: Uuid,
                Nama: Nama,
                Pemilik: Pemilik,
                Izin: Izin,
                PinDiatur: PinDiatur,
                PinGaram: PinGaram,
                PinNonce: PinNonce,
                PinSandi: PinSandi,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map(
                (e) => (
                  e.readTable<$StafTable, BarisStaf>(table),
                  BaseReferences<_$BasisDataKasir, $StafTable, BarisStaf>(db, table, e),
                ),
              )
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$StafTableProcessedTableManager =
    ProcessedTableManager<
      _$BasisDataKasir,
      $StafTable,
      BarisStaf,
      $$StafTableFilterComposer,
      $$StafTableOrderingComposer,
      $$StafTableAnnotationComposer,
      $$StafTableCreateCompanionBuilder,
      $$StafTableUpdateCompanionBuilder,
      (BarisStaf, BaseReferences<_$BasisDataKasir, $StafTable, BarisStaf>),
      BarisStaf,
      PrefetchHooks Function()
    >;
typedef $$KategoriKasTableCreateCompanionBuilder = KategoriKasCompanion Function({
  required String Uuid,
  required String Nama,
  required String Jenis,
  Value<int> rowid,
});
typedef $$KategoriKasTableUpdateCompanionBuilder = KategoriKasCompanion Function({
  Value<String> Uuid,
  Value<String> Nama,
  Value<String> Jenis,
  Value<int> rowid,
});

class $$KategoriKasTableFilterComposer extends Composer<_$BasisDataKasir, $KategoriKasTable> {
  $$KategoriKasTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get Uuid => $composableBuilder(column: $table.Uuid, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get Nama => $composableBuilder(column: $table.Nama, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get Jenis =>
      $composableBuilder(column: $table.Jenis, builder: (column) => ColumnFilters(column));
}

class $$KategoriKasTableOrderingComposer extends Composer<_$BasisDataKasir, $KategoriKasTable> {
  $$KategoriKasTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get Uuid =>
      $composableBuilder(column: $table.Uuid, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get Nama =>
      $composableBuilder(column: $table.Nama, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get Jenis =>
      $composableBuilder(column: $table.Jenis, builder: (column) => ColumnOrderings(column));
}

class $$KategoriKasTableAnnotationComposer extends Composer<_$BasisDataKasir, $KategoriKasTable> {
  $$KategoriKasTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get Uuid => $composableBuilder(column: $table.Uuid, builder: (column) => column);

  GeneratedColumn<String> get Nama => $composableBuilder(column: $table.Nama, builder: (column) => column);

  GeneratedColumn<String> get Jenis => $composableBuilder(column: $table.Jenis, builder: (column) => column);
}

class $$KategoriKasTableTableManager
    extends
        RootTableManager<
          _$BasisDataKasir,
          $KategoriKasTable,
          BarisKategoriKas,
          $$KategoriKasTableFilterComposer,
          $$KategoriKasTableOrderingComposer,
          $$KategoriKasTableAnnotationComposer,
          $$KategoriKasTableCreateCompanionBuilder,
          $$KategoriKasTableUpdateCompanionBuilder,
          (BarisKategoriKas, BaseReferences<_$BasisDataKasir, $KategoriKasTable, BarisKategoriKas>),
          BarisKategoriKas,
          PrefetchHooks Function()
        > {
  $$KategoriKasTableTableManager(_$BasisDataKasir db, $KategoriKasTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () => $$KategoriKasTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () => $$KategoriKasTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () => $$KategoriKasTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback: ({
            Value<String> Uuid = const Value.absent(),
            Value<String> Nama = const Value.absent(),
            Value<String> Jenis = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) => KategoriKasCompanion(Uuid: Uuid, Nama: Nama, Jenis: Jenis, rowid: rowid),
          createCompanionCallback: ({
            required String Uuid,
            required String Nama,
            required String Jenis,
            Value<int> rowid = const Value.absent(),
          }) => KategoriKasCompanion.insert(Uuid: Uuid, Nama: Nama, Jenis: Jenis, rowid: rowid),
          withReferenceMapper: (p0) => p0
              .map(
                (e) => (
                  e.readTable<$KategoriKasTable, BarisKategoriKas>(table),
                  BaseReferences<_$BasisDataKasir, $KategoriKasTable, BarisKategoriKas>(db, table, e),
                ),
              )
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$KategoriKasTableProcessedTableManager =
    ProcessedTableManager<
      _$BasisDataKasir,
      $KategoriKasTable,
      BarisKategoriKas,
      $$KategoriKasTableFilterComposer,
      $$KategoriKasTableOrderingComposer,
      $$KategoriKasTableAnnotationComposer,
      $$KategoriKasTableCreateCompanionBuilder,
      $$KategoriKasTableUpdateCompanionBuilder,
      (BarisKategoriKas, BaseReferences<_$BasisDataKasir, $KategoriKasTable, BarisKategoriKas>),
      BarisKategoriKas,
      PrefetchHooks Function()
    >;
typedef $$ShiftTableCreateCompanionBuilder = ShiftCompanion Function({
  required String Uuid,
  required String DibukaOleh,
  required String NamaKasir,
  required DateTime DibukaPada,
  required String KasAwal,
  Value<String?> PecahanKasAwal,
  required bool Bersama,
  required String Status,
  Value<int> rowid,
});
typedef $$ShiftTableUpdateCompanionBuilder = ShiftCompanion Function({
  Value<String> Uuid,
  Value<String> DibukaOleh,
  Value<String> NamaKasir,
  Value<DateTime> DibukaPada,
  Value<String> KasAwal,
  Value<String?> PecahanKasAwal,
  Value<bool> Bersama,
  Value<String> Status,
  Value<int> rowid,
});

final class $$ShiftTableReferences extends BaseReferences<_$BasisDataKasir, $ShiftTable, BarisShift> {
  $$ShiftTableReferences(super.$_db, super.$_table, super.$_typedResult);

  static MultiTypedResultKey<$MutasiKasTable, List<BarisMutasiKas>> _mutasiKasRefsTable(_$BasisDataKasir db) =>
      MultiTypedResultKey.fromTable(db.mutasiKas, aliasName: 'Shift__Uuid__MutasiKas__UuidShift');

  $$MutasiKasTableProcessedTableManager get mutasiKasRefs {
    final manager = $$MutasiKasTableTableManager(
      $_db,
      $_db.mutasiKas,
    ).filter((f) => f.UuidShift.Uuid.sqlEquals($_itemColumn<String>('Uuid')!));

    final cache = $_typedResult.readTableOrNull(_mutasiKasRefsTable($_db));
    return ProcessedTableManager(manager.$state.copyWith(prefetchedData: cache));
  }
}

class $$ShiftTableFilterComposer extends Composer<_$BasisDataKasir, $ShiftTable> {
  $$ShiftTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get Uuid => $composableBuilder(column: $table.Uuid, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get DibukaOleh =>
      $composableBuilder(column: $table.DibukaOleh, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get NamaKasir =>
      $composableBuilder(column: $table.NamaKasir, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get DibukaPada =>
      $composableBuilder(column: $table.DibukaPada, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get KasAwal =>
      $composableBuilder(column: $table.KasAwal, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get PecahanKasAwal =>
      $composableBuilder(column: $table.PecahanKasAwal, builder: (column) => ColumnFilters(column));

  ColumnFilters<bool> get Bersama =>
      $composableBuilder(column: $table.Bersama, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get Status =>
      $composableBuilder(column: $table.Status, builder: (column) => ColumnFilters(column));

  Expression<bool> mutasiKasRefs(Expression<bool> Function($$MutasiKasTableFilterComposer f) f) {
    final $$MutasiKasTableFilterComposer composer = $composerBuilder(
      composer: this,
      getCurrentColumn: (t) => t.Uuid,
      referencedTable: $db.mutasiKas,
      getReferencedColumn: (t) => t.UuidShift,
      builder: (joinBuilder, {$addJoinBuilderToRootComposer, $removeJoinBuilderFromRootComposer}) =>
          $$MutasiKasTableFilterComposer(
            $db: $db,
            $table: $db.mutasiKas,
            $addJoinBuilderToRootComposer: $addJoinBuilderToRootComposer,
            joinBuilder: joinBuilder,
            $removeJoinBuilderFromRootComposer: $removeJoinBuilderFromRootComposer,
          ),
    );
    return f(composer);
  }
}

class $$ShiftTableOrderingComposer extends Composer<_$BasisDataKasir, $ShiftTable> {
  $$ShiftTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get Uuid =>
      $composableBuilder(column: $table.Uuid, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get DibukaOleh =>
      $composableBuilder(column: $table.DibukaOleh, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get NamaKasir =>
      $composableBuilder(column: $table.NamaKasir, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get DibukaPada =>
      $composableBuilder(column: $table.DibukaPada, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get KasAwal =>
      $composableBuilder(column: $table.KasAwal, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get PecahanKasAwal =>
      $composableBuilder(column: $table.PecahanKasAwal, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<bool> get Bersama =>
      $composableBuilder(column: $table.Bersama, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get Status =>
      $composableBuilder(column: $table.Status, builder: (column) => ColumnOrderings(column));
}

class $$ShiftTableAnnotationComposer extends Composer<_$BasisDataKasir, $ShiftTable> {
  $$ShiftTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get Uuid => $composableBuilder(column: $table.Uuid, builder: (column) => column);

  GeneratedColumn<String> get DibukaOleh => $composableBuilder(column: $table.DibukaOleh, builder: (column) => column);

  GeneratedColumn<String> get NamaKasir => $composableBuilder(column: $table.NamaKasir, builder: (column) => column);

  GeneratedColumn<DateTime> get DibukaPada =>
      $composableBuilder(column: $table.DibukaPada, builder: (column) => column);

  GeneratedColumn<String> get KasAwal => $composableBuilder(column: $table.KasAwal, builder: (column) => column);

  GeneratedColumn<String> get PecahanKasAwal =>
      $composableBuilder(column: $table.PecahanKasAwal, builder: (column) => column);

  GeneratedColumn<bool> get Bersama => $composableBuilder(column: $table.Bersama, builder: (column) => column);

  GeneratedColumn<String> get Status => $composableBuilder(column: $table.Status, builder: (column) => column);

  Expression<T> mutasiKasRefs<T extends Object>(Expression<T> Function($$MutasiKasTableAnnotationComposer a) f) {
    final $$MutasiKasTableAnnotationComposer composer = $composerBuilder(
      composer: this,
      getCurrentColumn: (t) => t.Uuid,
      referencedTable: $db.mutasiKas,
      getReferencedColumn: (t) => t.UuidShift,
      builder: (joinBuilder, {$addJoinBuilderToRootComposer, $removeJoinBuilderFromRootComposer}) =>
          $$MutasiKasTableAnnotationComposer(
            $db: $db,
            $table: $db.mutasiKas,
            $addJoinBuilderToRootComposer: $addJoinBuilderToRootComposer,
            joinBuilder: joinBuilder,
            $removeJoinBuilderFromRootComposer: $removeJoinBuilderFromRootComposer,
          ),
    );
    return f(composer);
  }
}

class $$ShiftTableTableManager
    extends
        RootTableManager<
          _$BasisDataKasir,
          $ShiftTable,
          BarisShift,
          $$ShiftTableFilterComposer,
          $$ShiftTableOrderingComposer,
          $$ShiftTableAnnotationComposer,
          $$ShiftTableCreateCompanionBuilder,
          $$ShiftTableUpdateCompanionBuilder,
          (BarisShift, $$ShiftTableReferences),
          BarisShift,
          PrefetchHooks Function({bool mutasiKasRefs})
        > {
  $$ShiftTableTableManager(_$BasisDataKasir db, $ShiftTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () => $$ShiftTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () => $$ShiftTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () => $$ShiftTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> Uuid = const Value.absent(),
                Value<String> DibukaOleh = const Value.absent(),
                Value<String> NamaKasir = const Value.absent(),
                Value<DateTime> DibukaPada = const Value.absent(),
                Value<String> KasAwal = const Value.absent(),
                Value<String?> PecahanKasAwal = const Value.absent(),
                Value<bool> Bersama = const Value.absent(),
                Value<String> Status = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => ShiftCompanion(
                Uuid: Uuid,
                DibukaOleh: DibukaOleh,
                NamaKasir: NamaKasir,
                DibukaPada: DibukaPada,
                KasAwal: KasAwal,
                PecahanKasAwal: PecahanKasAwal,
                Bersama: Bersama,
                Status: Status,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String Uuid,
                required String DibukaOleh,
                required String NamaKasir,
                required DateTime DibukaPada,
                required String KasAwal,
                Value<String?> PecahanKasAwal = const Value.absent(),
                required bool Bersama,
                required String Status,
                Value<int> rowid = const Value.absent(),
              }) => ShiftCompanion.insert(
                Uuid: Uuid,
                DibukaOleh: DibukaOleh,
                NamaKasir: NamaKasir,
                DibukaPada: DibukaPada,
                KasAwal: KasAwal,
                PecahanKasAwal: PecahanKasAwal,
                Bersama: Bersama,
                Status: Status,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable<$ShiftTable, BarisShift>(table), $$ShiftTableReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: ({mutasiKasRefs = false}) {
            return PrefetchHooks(
              db: db,
              explicitlyWatchedTables: [if (mutasiKasRefs) db.mutasiKas],
              addJoins: null,
              getPrefetchedDataCallback: (items) async {
                return [
                  if (mutasiKasRefs)
                    await $_getPrefetchedData<BarisShift, $ShiftTable, BarisMutasiKas>(
                      currentTable: table,
                      referencedTable: $$ShiftTableReferences._mutasiKasRefsTable(db),
                      managerFromTypedResult: (p0) => $$ShiftTableReferences(db, table, p0).mutasiKasRefs,
                      referencedItemsForCurrentItem: (item, referencedItems) =>
                          referencedItems.where((e) => e.UuidShift == item.Uuid),
                      typedResults: items,
                    ),
                ];
              },
            );
          },
        ),
      );
}

typedef $$ShiftTableProcessedTableManager =
    ProcessedTableManager<
      _$BasisDataKasir,
      $ShiftTable,
      BarisShift,
      $$ShiftTableFilterComposer,
      $$ShiftTableOrderingComposer,
      $$ShiftTableAnnotationComposer,
      $$ShiftTableCreateCompanionBuilder,
      $$ShiftTableUpdateCompanionBuilder,
      (BarisShift, $$ShiftTableReferences),
      BarisShift,
      PrefetchHooks Function({bool mutasiKasRefs})
    >;
typedef $$MutasiKasTableCreateCompanionBuilder = MutasiKasCompanion Function({
  required String Uuid,
  required String UuidShift,
  required String Jenis,
  Value<String?> UuidKategori,
  Value<String?> NamaKategori,
  required String Jumlah,
  Value<String?> Catatan,
  required String DicatatOleh,
  required DateTime DicatatPada,
  Value<String?> DisetujuiOleh,
  Value<int> rowid,
});
typedef $$MutasiKasTableUpdateCompanionBuilder = MutasiKasCompanion Function({
  Value<String> Uuid,
  Value<String> UuidShift,
  Value<String> Jenis,
  Value<String?> UuidKategori,
  Value<String?> NamaKategori,
  Value<String> Jumlah,
  Value<String?> Catatan,
  Value<String> DicatatOleh,
  Value<DateTime> DicatatPada,
  Value<String?> DisetujuiOleh,
  Value<int> rowid,
});

final class $$MutasiKasTableReferences extends BaseReferences<_$BasisDataKasir, $MutasiKasTable, BarisMutasiKas> {
  $$MutasiKasTableReferences(super.$_db, super.$_table, super.$_typedResult);

  static $ShiftTable _UuidShiftTable(_$BasisDataKasir db) => db.shift.createAlias('MutasiKas__UuidShift__Shift__Uuid');

  $$ShiftTableProcessedTableManager get UuidShift {
    final $_column = $_itemColumn<String>('UuidShift')!;

    final manager = $$ShiftTableTableManager($_db, $_db.shift).filter((f) => f.Uuid.sqlEquals($_column));
    final item = $_typedResult.readTableOrNull(_UuidShiftTable($_db));
    if (item == null) return manager;
    return ProcessedTableManager(manager.$state.copyWith(prefetchedData: [item]));
  }
}

class $$MutasiKasTableFilterComposer extends Composer<_$BasisDataKasir, $MutasiKasTable> {
  $$MutasiKasTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get Uuid => $composableBuilder(column: $table.Uuid, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get Jenis =>
      $composableBuilder(column: $table.Jenis, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get UuidKategori =>
      $composableBuilder(column: $table.UuidKategori, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get NamaKategori =>
      $composableBuilder(column: $table.NamaKategori, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get Jumlah =>
      $composableBuilder(column: $table.Jumlah, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get Catatan =>
      $composableBuilder(column: $table.Catatan, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get DicatatOleh =>
      $composableBuilder(column: $table.DicatatOleh, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get DicatatPada =>
      $composableBuilder(column: $table.DicatatPada, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get DisetujuiOleh =>
      $composableBuilder(column: $table.DisetujuiOleh, builder: (column) => ColumnFilters(column));

  $$ShiftTableFilterComposer get UuidShift {
    final $$ShiftTableFilterComposer composer = $composerBuilder(
      composer: this,
      getCurrentColumn: (t) => t.UuidShift,
      referencedTable: $db.shift,
      getReferencedColumn: (t) => t.Uuid,
      builder: (joinBuilder, {$addJoinBuilderToRootComposer, $removeJoinBuilderFromRootComposer}) =>
          $$ShiftTableFilterComposer(
            $db: $db,
            $table: $db.shift,
            $addJoinBuilderToRootComposer: $addJoinBuilderToRootComposer,
            joinBuilder: joinBuilder,
            $removeJoinBuilderFromRootComposer: $removeJoinBuilderFromRootComposer,
          ),
    );
    return composer;
  }
}

class $$MutasiKasTableOrderingComposer extends Composer<_$BasisDataKasir, $MutasiKasTable> {
  $$MutasiKasTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get Uuid =>
      $composableBuilder(column: $table.Uuid, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get Jenis =>
      $composableBuilder(column: $table.Jenis, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get UuidKategori =>
      $composableBuilder(column: $table.UuidKategori, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get NamaKategori =>
      $composableBuilder(column: $table.NamaKategori, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get Jumlah =>
      $composableBuilder(column: $table.Jumlah, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get Catatan =>
      $composableBuilder(column: $table.Catatan, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get DicatatOleh =>
      $composableBuilder(column: $table.DicatatOleh, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get DicatatPada =>
      $composableBuilder(column: $table.DicatatPada, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get DisetujuiOleh =>
      $composableBuilder(column: $table.DisetujuiOleh, builder: (column) => ColumnOrderings(column));

  $$ShiftTableOrderingComposer get UuidShift {
    final $$ShiftTableOrderingComposer composer = $composerBuilder(
      composer: this,
      getCurrentColumn: (t) => t.UuidShift,
      referencedTable: $db.shift,
      getReferencedColumn: (t) => t.Uuid,
      builder: (joinBuilder, {$addJoinBuilderToRootComposer, $removeJoinBuilderFromRootComposer}) =>
          $$ShiftTableOrderingComposer(
            $db: $db,
            $table: $db.shift,
            $addJoinBuilderToRootComposer: $addJoinBuilderToRootComposer,
            joinBuilder: joinBuilder,
            $removeJoinBuilderFromRootComposer: $removeJoinBuilderFromRootComposer,
          ),
    );
    return composer;
  }
}

class $$MutasiKasTableAnnotationComposer extends Composer<_$BasisDataKasir, $MutasiKasTable> {
  $$MutasiKasTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get Uuid => $composableBuilder(column: $table.Uuid, builder: (column) => column);

  GeneratedColumn<String> get Jenis => $composableBuilder(column: $table.Jenis, builder: (column) => column);

  GeneratedColumn<String> get UuidKategori =>
      $composableBuilder(column: $table.UuidKategori, builder: (column) => column);

  GeneratedColumn<String> get NamaKategori =>
      $composableBuilder(column: $table.NamaKategori, builder: (column) => column);

  GeneratedColumn<String> get Jumlah => $composableBuilder(column: $table.Jumlah, builder: (column) => column);

  GeneratedColumn<String> get Catatan => $composableBuilder(column: $table.Catatan, builder: (column) => column);

  GeneratedColumn<String> get DicatatOleh =>
      $composableBuilder(column: $table.DicatatOleh, builder: (column) => column);

  GeneratedColumn<DateTime> get DicatatPada =>
      $composableBuilder(column: $table.DicatatPada, builder: (column) => column);

  GeneratedColumn<String> get DisetujuiOleh =>
      $composableBuilder(column: $table.DisetujuiOleh, builder: (column) => column);

  $$ShiftTableAnnotationComposer get UuidShift {
    final $$ShiftTableAnnotationComposer composer = $composerBuilder(
      composer: this,
      getCurrentColumn: (t) => t.UuidShift,
      referencedTable: $db.shift,
      getReferencedColumn: (t) => t.Uuid,
      builder: (joinBuilder, {$addJoinBuilderToRootComposer, $removeJoinBuilderFromRootComposer}) =>
          $$ShiftTableAnnotationComposer(
            $db: $db,
            $table: $db.shift,
            $addJoinBuilderToRootComposer: $addJoinBuilderToRootComposer,
            joinBuilder: joinBuilder,
            $removeJoinBuilderFromRootComposer: $removeJoinBuilderFromRootComposer,
          ),
    );
    return composer;
  }
}

class $$MutasiKasTableTableManager
    extends
        RootTableManager<
          _$BasisDataKasir,
          $MutasiKasTable,
          BarisMutasiKas,
          $$MutasiKasTableFilterComposer,
          $$MutasiKasTableOrderingComposer,
          $$MutasiKasTableAnnotationComposer,
          $$MutasiKasTableCreateCompanionBuilder,
          $$MutasiKasTableUpdateCompanionBuilder,
          (BarisMutasiKas, $$MutasiKasTableReferences),
          BarisMutasiKas,
          PrefetchHooks Function({bool UuidShift})
        > {
  $$MutasiKasTableTableManager(_$BasisDataKasir db, $MutasiKasTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () => $$MutasiKasTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () => $$MutasiKasTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () => $$MutasiKasTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> Uuid = const Value.absent(),
                Value<String> UuidShift = const Value.absent(),
                Value<String> Jenis = const Value.absent(),
                Value<String?> UuidKategori = const Value.absent(),
                Value<String?> NamaKategori = const Value.absent(),
                Value<String> Jumlah = const Value.absent(),
                Value<String?> Catatan = const Value.absent(),
                Value<String> DicatatOleh = const Value.absent(),
                Value<DateTime> DicatatPada = const Value.absent(),
                Value<String?> DisetujuiOleh = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => MutasiKasCompanion(
                Uuid: Uuid,
                UuidShift: UuidShift,
                Jenis: Jenis,
                UuidKategori: UuidKategori,
                NamaKategori: NamaKategori,
                Jumlah: Jumlah,
                Catatan: Catatan,
                DicatatOleh: DicatatOleh,
                DicatatPada: DicatatPada,
                DisetujuiOleh: DisetujuiOleh,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String Uuid,
                required String UuidShift,
                required String Jenis,
                Value<String?> UuidKategori = const Value.absent(),
                Value<String?> NamaKategori = const Value.absent(),
                required String Jumlah,
                Value<String?> Catatan = const Value.absent(),
                required String DicatatOleh,
                required DateTime DicatatPada,
                Value<String?> DisetujuiOleh = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => MutasiKasCompanion.insert(
                Uuid: Uuid,
                UuidShift: UuidShift,
                Jenis: Jenis,
                UuidKategori: UuidKategori,
                NamaKategori: NamaKategori,
                Jumlah: Jumlah,
                Catatan: Catatan,
                DicatatOleh: DicatatOleh,
                DicatatPada: DicatatPada,
                DisetujuiOleh: DisetujuiOleh,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map(
                (e) => (e.readTable<$MutasiKasTable, BarisMutasiKas>(table), $$MutasiKasTableReferences(db, table, e)),
              )
              .toList(),
          prefetchHooksCallback: ({UuidShift = false}) {
            return PrefetchHooks(
              db: db,
              explicitlyWatchedTables: [],
              addJoins:
                  <
                    T extends TableManagerState<
                      dynamic,
                      dynamic,
                      dynamic,
                      dynamic,
                      dynamic,
                      dynamic,
                      dynamic,
                      dynamic,
                      dynamic,
                      dynamic,
                      dynamic
                    >
                  >(state) {
                    if (UuidShift) {
                      state = state.withJoin(
                        currentTable: table,
                        currentColumn: table.UuidShift,
                        referencedTable: $$MutasiKasTableReferences._UuidShiftTable(db),
                        referencedColumn: $$MutasiKasTableReferences._UuidShiftTable(db).Uuid,
                      ) as T;
                    }

                    return state;
                  },
              getPrefetchedDataCallback: (items) async {
                return [];
              },
            );
          },
        ),
      );
}

typedef $$MutasiKasTableProcessedTableManager =
    ProcessedTableManager<
      _$BasisDataKasir,
      $MutasiKasTable,
      BarisMutasiKas,
      $$MutasiKasTableFilterComposer,
      $$MutasiKasTableOrderingComposer,
      $$MutasiKasTableAnnotationComposer,
      $$MutasiKasTableCreateCompanionBuilder,
      $$MutasiKasTableUpdateCompanionBuilder,
      (BarisMutasiKas, $$MutasiKasTableReferences),
      BarisMutasiKas,
      PrefetchHooks Function({bool UuidShift})
    >;
typedef $$OutboxTableCreateCompanionBuilder = OutboxCompanion Function({
  Value<int> Id,
  required String Uuid,
  required String Jenis,
  required String Data,
  required String Status,
  Value<int> Percobaan,
  Value<String?> KodeGalat,
  Value<String?> PesanGalat,
  required DateTime DibuatPada,
  required DateTime BerikutnyaPada,
});
typedef $$OutboxTableUpdateCompanionBuilder = OutboxCompanion Function({
  Value<int> Id,
  Value<String> Uuid,
  Value<String> Jenis,
  Value<String> Data,
  Value<String> Status,
  Value<int> Percobaan,
  Value<String?> KodeGalat,
  Value<String?> PesanGalat,
  Value<DateTime> DibuatPada,
  Value<DateTime> BerikutnyaPada,
});

class $$OutboxTableFilterComposer extends Composer<_$BasisDataKasir, $OutboxTable> {
  $$OutboxTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get Id => $composableBuilder(column: $table.Id, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get Uuid => $composableBuilder(column: $table.Uuid, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get Jenis =>
      $composableBuilder(column: $table.Jenis, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get Data => $composableBuilder(column: $table.Data, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get Status =>
      $composableBuilder(column: $table.Status, builder: (column) => ColumnFilters(column));

  ColumnFilters<int> get Percobaan =>
      $composableBuilder(column: $table.Percobaan, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get KodeGalat =>
      $composableBuilder(column: $table.KodeGalat, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get PesanGalat =>
      $composableBuilder(column: $table.PesanGalat, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get DibuatPada =>
      $composableBuilder(column: $table.DibuatPada, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get BerikutnyaPada =>
      $composableBuilder(column: $table.BerikutnyaPada, builder: (column) => ColumnFilters(column));
}

class $$OutboxTableOrderingComposer extends Composer<_$BasisDataKasir, $OutboxTable> {
  $$OutboxTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get Id => $composableBuilder(column: $table.Id, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get Uuid =>
      $composableBuilder(column: $table.Uuid, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get Jenis =>
      $composableBuilder(column: $table.Jenis, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get Data =>
      $composableBuilder(column: $table.Data, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get Status =>
      $composableBuilder(column: $table.Status, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<int> get Percobaan =>
      $composableBuilder(column: $table.Percobaan, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get KodeGalat =>
      $composableBuilder(column: $table.KodeGalat, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get PesanGalat =>
      $composableBuilder(column: $table.PesanGalat, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get DibuatPada =>
      $composableBuilder(column: $table.DibuatPada, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get BerikutnyaPada =>
      $composableBuilder(column: $table.BerikutnyaPada, builder: (column) => ColumnOrderings(column));
}

class $$OutboxTableAnnotationComposer extends Composer<_$BasisDataKasir, $OutboxTable> {
  $$OutboxTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get Id => $composableBuilder(column: $table.Id, builder: (column) => column);

  GeneratedColumn<String> get Uuid => $composableBuilder(column: $table.Uuid, builder: (column) => column);

  GeneratedColumn<String> get Jenis => $composableBuilder(column: $table.Jenis, builder: (column) => column);

  GeneratedColumn<String> get Data => $composableBuilder(column: $table.Data, builder: (column) => column);

  GeneratedColumn<String> get Status => $composableBuilder(column: $table.Status, builder: (column) => column);

  GeneratedColumn<int> get Percobaan => $composableBuilder(column: $table.Percobaan, builder: (column) => column);

  GeneratedColumn<String> get KodeGalat => $composableBuilder(column: $table.KodeGalat, builder: (column) => column);

  GeneratedColumn<String> get PesanGalat => $composableBuilder(column: $table.PesanGalat, builder: (column) => column);

  GeneratedColumn<DateTime> get DibuatPada =>
      $composableBuilder(column: $table.DibuatPada, builder: (column) => column);

  GeneratedColumn<DateTime> get BerikutnyaPada =>
      $composableBuilder(column: $table.BerikutnyaPada, builder: (column) => column);
}

class $$OutboxTableTableManager
    extends
        RootTableManager<
          _$BasisDataKasir,
          $OutboxTable,
          BarisOutbox,
          $$OutboxTableFilterComposer,
          $$OutboxTableOrderingComposer,
          $$OutboxTableAnnotationComposer,
          $$OutboxTableCreateCompanionBuilder,
          $$OutboxTableUpdateCompanionBuilder,
          (BarisOutbox, BaseReferences<_$BasisDataKasir, $OutboxTable, BarisOutbox>),
          BarisOutbox,
          PrefetchHooks Function()
        > {
  $$OutboxTableTableManager(_$BasisDataKasir db, $OutboxTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () => $$OutboxTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () => $$OutboxTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () => $$OutboxTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> Id = const Value.absent(),
                Value<String> Uuid = const Value.absent(),
                Value<String> Jenis = const Value.absent(),
                Value<String> Data = const Value.absent(),
                Value<String> Status = const Value.absent(),
                Value<int> Percobaan = const Value.absent(),
                Value<String?> KodeGalat = const Value.absent(),
                Value<String?> PesanGalat = const Value.absent(),
                Value<DateTime> DibuatPada = const Value.absent(),
                Value<DateTime> BerikutnyaPada = const Value.absent(),
              }) => OutboxCompanion(
                Id: Id,
                Uuid: Uuid,
                Jenis: Jenis,
                Data: Data,
                Status: Status,
                Percobaan: Percobaan,
                KodeGalat: KodeGalat,
                PesanGalat: PesanGalat,
                DibuatPada: DibuatPada,
                BerikutnyaPada: BerikutnyaPada,
              ),
          createCompanionCallback:
              ({
                Value<int> Id = const Value.absent(),
                required String Uuid,
                required String Jenis,
                required String Data,
                required String Status,
                Value<int> Percobaan = const Value.absent(),
                Value<String?> KodeGalat = const Value.absent(),
                Value<String?> PesanGalat = const Value.absent(),
                required DateTime DibuatPada,
                required DateTime BerikutnyaPada,
              }) => OutboxCompanion.insert(
                Id: Id,
                Uuid: Uuid,
                Jenis: Jenis,
                Data: Data,
                Status: Status,
                Percobaan: Percobaan,
                KodeGalat: KodeGalat,
                PesanGalat: PesanGalat,
                DibuatPada: DibuatPada,
                BerikutnyaPada: BerikutnyaPada,
              ),
          withReferenceMapper: (p0) => p0
              .map(
                (e) => (
                  e.readTable<$OutboxTable, BarisOutbox>(table),
                  BaseReferences<_$BasisDataKasir, $OutboxTable, BarisOutbox>(db, table, e),
                ),
              )
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$OutboxTableProcessedTableManager =
    ProcessedTableManager<
      _$BasisDataKasir,
      $OutboxTable,
      BarisOutbox,
      $$OutboxTableFilterComposer,
      $$OutboxTableOrderingComposer,
      $$OutboxTableAnnotationComposer,
      $$OutboxTableCreateCompanionBuilder,
      $$OutboxTableUpdateCompanionBuilder,
      (BarisOutbox, BaseReferences<_$BasisDataKasir, $OutboxTable, BarisOutbox>),
      BarisOutbox,
      PrefetchHooks Function()
    >;
typedef $$PercobaanPinTableCreateCompanionBuilder = PercobaanPinCompanion Function({
  required String UuidPengguna,
  required int JumlahGagal,
  Value<DateTime?> TerkunciSampai,
  Value<int> rowid,
});
typedef $$PercobaanPinTableUpdateCompanionBuilder = PercobaanPinCompanion Function({
  Value<String> UuidPengguna,
  Value<int> JumlahGagal,
  Value<DateTime?> TerkunciSampai,
  Value<int> rowid,
});

class $$PercobaanPinTableFilterComposer extends Composer<_$BasisDataKasir, $PercobaanPinTable> {
  $$PercobaanPinTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get UuidPengguna =>
      $composableBuilder(column: $table.UuidPengguna, builder: (column) => ColumnFilters(column));

  ColumnFilters<int> get JumlahGagal =>
      $composableBuilder(column: $table.JumlahGagal, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get TerkunciSampai =>
      $composableBuilder(column: $table.TerkunciSampai, builder: (column) => ColumnFilters(column));
}

class $$PercobaanPinTableOrderingComposer extends Composer<_$BasisDataKasir, $PercobaanPinTable> {
  $$PercobaanPinTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get UuidPengguna =>
      $composableBuilder(column: $table.UuidPengguna, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<int> get JumlahGagal =>
      $composableBuilder(column: $table.JumlahGagal, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get TerkunciSampai =>
      $composableBuilder(column: $table.TerkunciSampai, builder: (column) => ColumnOrderings(column));
}

class $$PercobaanPinTableAnnotationComposer extends Composer<_$BasisDataKasir, $PercobaanPinTable> {
  $$PercobaanPinTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get UuidPengguna =>
      $composableBuilder(column: $table.UuidPengguna, builder: (column) => column);

  GeneratedColumn<int> get JumlahGagal => $composableBuilder(column: $table.JumlahGagal, builder: (column) => column);

  GeneratedColumn<DateTime> get TerkunciSampai =>
      $composableBuilder(column: $table.TerkunciSampai, builder: (column) => column);
}

class $$PercobaanPinTableTableManager
    extends
        RootTableManager<
          _$BasisDataKasir,
          $PercobaanPinTable,
          BarisPercobaanPin,
          $$PercobaanPinTableFilterComposer,
          $$PercobaanPinTableOrderingComposer,
          $$PercobaanPinTableAnnotationComposer,
          $$PercobaanPinTableCreateCompanionBuilder,
          $$PercobaanPinTableUpdateCompanionBuilder,
          (BarisPercobaanPin, BaseReferences<_$BasisDataKasir, $PercobaanPinTable, BarisPercobaanPin>),
          BarisPercobaanPin,
          PrefetchHooks Function()
        > {
  $$PercobaanPinTableTableManager(_$BasisDataKasir db, $PercobaanPinTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () => $$PercobaanPinTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () => $$PercobaanPinTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () => $$PercobaanPinTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> UuidPengguna = const Value.absent(),
                Value<int> JumlahGagal = const Value.absent(),
                Value<DateTime?> TerkunciSampai = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => PercobaanPinCompanion(
                UuidPengguna: UuidPengguna,
                JumlahGagal: JumlahGagal,
                TerkunciSampai: TerkunciSampai,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String UuidPengguna,
                required int JumlahGagal,
                Value<DateTime?> TerkunciSampai = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => PercobaanPinCompanion.insert(
                UuidPengguna: UuidPengguna,
                JumlahGagal: JumlahGagal,
                TerkunciSampai: TerkunciSampai,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map(
                (e) => (
                  e.readTable<$PercobaanPinTable, BarisPercobaanPin>(table),
                  BaseReferences<_$BasisDataKasir, $PercobaanPinTable, BarisPercobaanPin>(db, table, e),
                ),
              )
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$PercobaanPinTableProcessedTableManager =
    ProcessedTableManager<
      _$BasisDataKasir,
      $PercobaanPinTable,
      BarisPercobaanPin,
      $$PercobaanPinTableFilterComposer,
      $$PercobaanPinTableOrderingComposer,
      $$PercobaanPinTableAnnotationComposer,
      $$PercobaanPinTableCreateCompanionBuilder,
      $$PercobaanPinTableUpdateCompanionBuilder,
      (BarisPercobaanPin, BaseReferences<_$BasisDataKasir, $PercobaanPinTable, BarisPercobaanPin>),
      BarisPercobaanPin,
      PrefetchHooks Function()
    >;

class $BasisDataKasirManager {
  final _$BasisDataKasir _db;
  $BasisDataKasirManager(this._db);
  $$PengaturanTableTableManager get pengaturan => $$PengaturanTableTableManager(_db, _db.pengaturan);
  $$StafTableTableManager get staf => $$StafTableTableManager(_db, _db.staf);
  $$KategoriKasTableTableManager get kategoriKas => $$KategoriKasTableTableManager(_db, _db.kategoriKas);
  $$ShiftTableTableManager get shift => $$ShiftTableTableManager(_db, _db.shift);
  $$MutasiKasTableTableManager get mutasiKas => $$MutasiKasTableTableManager(_db, _db.mutasiKas);
  $$OutboxTableTableManager get outbox => $$OutboxTableTableManager(_db, _db.outbox);
  $$PercobaanPinTableTableManager get percobaanPin => $$PercobaanPinTableTableManager(_db, _db.percobaanPin);
}
