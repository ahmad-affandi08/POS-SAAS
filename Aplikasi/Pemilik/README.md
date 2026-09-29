# Pemilik

Aplikasi Owner Flutter. Lihat PRD §17 dan `.claude/rules/Flutter.md`.

```bash
flutter run -t lib/UtamaDev.dart        # juga: UtamaStaging.dart, UtamaProduksi.dart
flutter test
```

## Firebase Cloud Messaging

- Android: pasang `android/app/google-services.json`; plugin Google Services diterapkan otomatis bila berkas ada.
- iOS: pasang `ios/Runner/GoogleService-Info.plist`, aktifkan Push Notifications dan Background Modes → Remote notifications di Xcode.
- Backend: aktifkan integrasi Push/Fcm di Platform Pengelola dengan JSON akun layanan Firebase, lalu pastikan cron scheduler dan worker antrean berjalan.
