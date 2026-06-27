import 'package:flutter/material.dart';

class AvailabilityCard extends StatefulWidget {
  const AvailabilityCard({
    super.key,
    required this.category,
    this.suggestedPrice,
  });

  final Map<String, dynamic> category;
  final int? suggestedPrice;

  @override
  State<AvailabilityCard> createState() => _AvailabilityCardState();
}

class _AvailabilityCardState extends State<AvailabilityCard> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    final category = widget.category;
    final available = category['available'] ?? 0;
    final total = category['total'] ?? 0;
    final basePrice = category['base_price'] ?? category['fixed_price'] ?? 0;
    final isFixedPrice = category['is_fixed_price'] == true;
    final occupancy = total > 0 ? ((total - available) / total) : 0.0;
    final progress = occupancy.clamp(0.0, 1.0);
    final isFull = available == 0;
    final icon = isFull ? Icons.no_meeting_room : Icons.meeting_room;
    final color = isFull ? const Color(0xFF9F1239) : const Color(0xFF0F766E);
    final softColor = isFull ? const Color(0xFFFFEEF2) : const Color(0xFFE8F7F2);
    final availableRoomNumbers = List<String>.from(
      category['available_room_numbers'] as List<dynamic>? ?? const [],
    );

    return Material(
      color: Colors.white.withValues(alpha: 0.88),
      elevation: 0,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(24),
        side: BorderSide(color: Colors.white.withValues(alpha: 0.92)),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        borderRadius: BorderRadius.circular(24),
        onTap: availableRoomNumbers.isEmpty
            ? null
            : () => setState(() => _expanded = !_expanded),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: softColor,
                      borderRadius: BorderRadius.circular(18),
                    ),
                    child: Icon(icon, color: color, size: 24),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          category['type']?.toString() ?? '',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Color(0xFF0F172A),
                            fontWeight: FontWeight.w900,
                            fontSize: 16,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          category['model']?.toString() ?? '',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 12,
                            color: Color(0xFF64748B),
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                  _AvailabilityRing(
                    available: available,
                    total: total,
                    color: color,
                    progress: progress,
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Text(
                    '$available sur $total libres',
                    style: TextStyle(
                      color: color,
                      fontSize: 13,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const Spacer(),
                  Text(
                    '${(progress * 100).round()}%',
                    style: const TextStyle(
                      color: Color(0xFF64748B),
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'PRIX BASE',
                        style: TextStyle(
                          color: Color(0xFF64748B),
                          fontSize: 9,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      Text(
                        '$basePrice Ar',
                        style: const TextStyle(
                          color: Color(0xFF0F172A),
                          fontWeight: FontWeight.w900,
                          fontSize: 14,
                        ),
                      ),
                    ],
                  ),
                  if (widget.suggestedPrice != null &&
                      widget.suggestedPrice != basePrice)
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        const Text(
                          'SUGGÉRÉ (IA)',
                          style: TextStyle(
                            color: Color(0xFF0F766E),
                            fontSize: 9,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        Text(
                          '${widget.suggestedPrice} Ar',
                          style: const TextStyle(
                            color: Color(0xFF0F766E),
                            fontWeight: FontWeight.w900,
                            fontSize: 14,
                          ),
                        ),
                      ],
                    )
                  else if (isFixedPrice)
                    const Text(
                      'PRIX FIXE',
                      style: TextStyle(
                        color: Color(0xFF64748B),
                        fontSize: 10,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                ],
              ),
              if (availableRoomNumbers.isNotEmpty) ...[
                const SizedBox(height: 12),
                AnimatedCrossFade(
                  crossFadeState: _expanded
                      ? CrossFadeState.showSecond
                      : CrossFadeState.showFirst,
                  duration: const Duration(milliseconds: 220),
                  firstCurve: Curves.easeOut,
                  secondCurve: Curves.easeOut,
                  firstChild: Row(
                    children: [
                      Text(
                        _expanded ? 'Masquer les numéros' : 'Cliquer pour voir les numéros libres',
                        style: TextStyle(
                          color: color.withValues(alpha: 0.78),
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const Spacer(),
                      Icon(
                        _expanded ? Icons.expand_less : Icons.expand_more,
                        color: color.withValues(alpha: 0.72),
                        size: 18,
                      ),
                    ],
                  ),
                  secondChild: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Text(
                            'Numéros libres',
                            style: TextStyle(
                              color: color,
                              fontSize: 12,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const Spacer(),
                          Icon(
                            Icons.expand_less,
                            color: color.withValues(alpha: 0.72),
                            size: 18,
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: availableRoomNumbers
                            .map(
                              (roomNumber) => Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 7,
                                ),
                                decoration: BoxDecoration(
                                  color: softColor,
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: Text(
                                  roomNumber,
                                  style: TextStyle(
                                    color: color,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ),
                            )
                            .toList(),
                      ),
                    ],
                  ),
                ),
              ] else ...[
                const SizedBox(height: 12),
                Text(
                  'Aucune chambre libre dans cette catégorie',
                  style: TextStyle(
                    color: color.withValues(alpha: 0.72),
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _AvailabilityRing extends StatelessWidget {
  const _AvailabilityRing({
    required this.available,
    required this.total,
    required this.color,
    required this.progress,
  });

  final int available;
  final int total;
  final Color color;
  final double progress;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 72,
      height: 72,
      child: TweenAnimationBuilder<double>(
        tween: Tween(begin: 0, end: progress),
        duration: const Duration(milliseconds: 450),
        curve: Curves.easeOutCubic,
        builder: (context, value, _) {
          return Stack(
            alignment: Alignment.center,
            children: [
              SizedBox.expand(
                child: CircularProgressIndicator(
                  value: 1,
                  strokeWidth: 6,
                  backgroundColor: const Color(0xFFE2E8F0),
                  valueColor: AlwaysStoppedAnimation<Color>(
                    color.withValues(alpha: 0.10),
                  ),
                ),
              ),
              SizedBox.expand(
                child: CircularProgressIndicator(
                  value: value,
                  strokeWidth: 6,
                  backgroundColor: Colors.transparent,
                  valueColor: AlwaysStoppedAnimation<Color>(color),
                ),
              ),
              Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    available.toString(),
                    style: TextStyle(
                      color: color,
                      fontSize: 19,
                      fontWeight: FontWeight.w900,
                      height: 1,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'libres',
                    style: TextStyle(
                      color: color.withValues(alpha: 0.76),
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ],
          );
        },
      ),
    );
  }
}
