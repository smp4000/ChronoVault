{{--
=============================================================================
Filament-Modal: Gebotsliste eines Auktionsloses (Modul 8b)
=============================================================================
Erwartet: $bids (Collection<AuctionBid>, höchste zuerst).
Nur intern sichtbar — enthält Bieterdaten (Name/E-Mail/IP)!
=============================================================================
--}}
<div class="space-y-3">
    @if ($bids->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Noch keine Online-Gebote auf dieses Los.
        </p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <th class="py-2 pe-4">Gebot</th>
                    <th class="py-2 pe-4">Bieter</th>
                    <th class="py-2 pe-4">Kontakt</th>
                    <th class="py-2">Zeitpunkt</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bids as $bid)
                    <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800 {{ $loop->first ? 'font-semibold' : '' }}">
                        <td class="py-2 pe-4 whitespace-nowrap">
                            {{ number_format((float) $bid->amount, 0, ',', '.') }} €
                            @if ($loop->first)
                                <span class="ms-1 rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-400">Höchstgebot</span>
                            @endif
                        </td>
                        <td class="py-2 pe-4">{{ $bid->bidder_name }}</td>
                        <td class="py-2 pe-4">
                            {{ $bid->bidder_email }}
                            @if ($bid->bidder_phone)
                                <span class="text-gray-400">· {{ $bid->bidder_phone }}</span>
                            @endif
                        </td>
                        <td class="py-2 whitespace-nowrap text-gray-500 dark:text-gray-400">
                            {{ $bid->created_at->format('d.m.Y H:i') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
