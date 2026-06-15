String formatPrice(dynamic price) {
  if (price == null) return '0';
  final value = price.toString();
  final reg = RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))');
  return value.replaceAllMapped(reg, (Match match) => '${match[1]}.');
}
