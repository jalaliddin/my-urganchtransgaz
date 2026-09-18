# Flutter's own embedding + plugins ship their own consumer ProGuard rules
# (geolocator, image_picker, file_picker, flutter_secure_storage, etc. all
# bundle theirs), so no extra keep rules are needed here today. Add any
# future manual keep rules below if R8 ever strips something reflectively
# accessed (e.g. a class only referenced via a plugin's platform channel).
