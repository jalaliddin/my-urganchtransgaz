import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:go_router/go_router.dart';

import '../../../core/locale/language_switcher.dart';
import '../../../core/models/user.dart';
import '../../../core/theme/status_colors.dart';
import '../../../core/widgets/responsive_body.dart';
import '../../../core/widgets/section.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../notifications/presentation/notifications_controller.dart';

/// Everything that isn't a bottom-bar destination, grouped the way system
/// settings screens are: who you are, your records, then app settings.
class MoreScreen extends ConsumerWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final status = StatusColors.of(context);
    final user = ref.watch(authControllerProvider).value;
    final unreadCount = ref.watch(unreadNotificationsCountProvider).value ?? 0;
    const purple = Color(0xFF6D5DD3);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.navMore)),
      body: ResponsiveBody(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
          children: [
            if (user != null) ...[
              _ProfileHeader(user: user, onTap: () => context.push('/profile')),
              const SizedBox(height: 16),
            ],
            GroupCard(
              dividerIndent: 68,
              children: [
                _MenuRow(
                  icon: Icons.calendar_month_outlined,
                  tone: status.fg(StatusTone.info),
                  title: l10n.calendarTitle,
                  onTap: () => context.push('/calendar'),
                ),
                _MenuRow(
                  icon: Icons.event_busy_outlined,
                  tone: status.fg(StatusTone.warning),
                  title: l10n.absencesTitle,
                  onTap: () => context.push('/absences'),
                ),
                _MenuRow(
                  icon: Icons.folder_outlined,
                  tone: scheme.secondary,
                  title: l10n.documentsTitle,
                  onTap: () => context.push('/documents'),
                ),
                _MenuRow(
                  icon: Icons.insights_outlined,
                  tone: purple,
                  title: l10n.kpiTitle,
                  onTap: () => context.push('/kpi'),
                ),
                _MenuRow(
                  icon: Icons.school_outlined,
                  tone: status.fg(StatusTone.success),
                  title: l10n.examsTitle,
                  onTap: () => context.push('/exams'),
                ),
              ],
            ),
            const SizedBox(height: 16),
            GroupCard(
              dividerIndent: 68,
              children: [
                _MenuRow(
                  icon: Icons.campaign_outlined,
                  tone: status.fg(StatusTone.warning),
                  title: l10n.announcementsTitle,
                  onTap: () => context.push('/announcements'),
                ),
                _MenuRow(
                  icon: Icons.notifications_outlined,
                  tone: status.fg(StatusTone.danger),
                  title: l10n.notificationsTitle,
                  badge: unreadCount > 0 ? '$unreadCount' : null,
                  onTap: () => context.push('/notifications'),
                ),
              ],
            ),
            const SizedBox(height: 16),
            GroupCard(
              dividerIndent: 68,
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          IconBadge(icon: Icons.language, size: 36, color: scheme.primary, background: scheme.primaryContainer),
                          const SizedBox(width: 16),
                          Text(l10n.settingsLanguage, style: Theme.of(context).textTheme.bodyLarge!.copyWith(fontWeight: FontWeight.w500)),
                        ],
                      ),
                      const SizedBox(height: 12),
                      const LanguageSwitcher(expand: true),
                    ],
                  ),
                ),
                _MenuRow(
                  icon: Icons.lock_outline,
                  tone: scheme.primary,
                  title: l10n.authChangePassword,
                  onTap: () => context.push('/change-password'),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Card(
              child: ListTile(
                leading: IconBadge(
                  icon: Icons.logout,
                  size: 36,
                  color: scheme.error,
                  background: status.bg(StatusTone.danger),
                ),
                title: Text(l10n.commonLogout, style: TextStyle(color: scheme.error, fontWeight: FontWeight.w600)),
                onTap: () => ref.read(authControllerProvider.notifier).logout(),
              ),
            ),
            const SizedBox(height: 28),
            Center(
              child: Opacity(
                opacity: 0.45,
                child: SvgPicture.asset('assets/images/logo.svg', height: 30),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MenuRow extends StatelessWidget {
  const _MenuRow({required this.icon, required this.tone, required this.title, required this.onTap, this.badge});

  final IconData icon;
  final Color tone;
  final String title;
  final VoidCallback onTap;
  final String? badge;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return ListTile(
      leading: IconBadge(icon: icon, size: 36, color: tone, background: tone.withValues(alpha: 0.12)),
      title: Text(title),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (badge != null)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(color: scheme.error, borderRadius: BorderRadius.circular(99)),
              child: Text(badge!, style: Theme.of(context).textTheme.labelSmall!.copyWith(color: scheme.onError)),
            ),
          const SizedBox(width: 4),
          Icon(Icons.chevron_right, color: scheme.onSurfaceVariant),
        ],
      ),
      onTap: onTap,
    );
  }
}

class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({required this.user, required this.onTap});

  final User user;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final employee = user.employee;
    final initials = [employee?.firstName, employee?.lastName]
        .whereType<String>()
        .where((part) => part.isNotEmpty)
        .map((part) => part[0].toUpperCase())
        .join();

    return Card(
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              CircleAvatar(
                radius: 30,
                backgroundColor: scheme.primaryContainer,
                child: Text(
                  initials.isEmpty ? (user.name.isNotEmpty ? user.name[0].toUpperCase() : '?') : initials,
                  style: text.titleLarge!.copyWith(color: scheme.onPrimaryContainer),
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(user.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: text.titleMedium),
                    if (employee != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        employee.position?.title ?? employee.employeeNumber,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: text.bodyMedium!.copyWith(color: scheme.onSurfaceVariant),
                      ),
                      Text(
                        employee.employeeNumber,
                        style: text.bodySmall!.copyWith(color: scheme.onSurfaceVariant),
                      ),
                    ],
                  ],
                ),
              ),
              Icon(Icons.chevron_right, color: scheme.onSurfaceVariant),
            ],
          ),
        ),
      ),
    );
  }
}
