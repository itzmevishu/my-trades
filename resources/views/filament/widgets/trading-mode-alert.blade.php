<x-filament-widgets::widget>
    @php
        $data = $this->getData();
        $alertClass = match($data['alert_type']) {
            'danger' => 'bg-red-50 border-red-200 text-red-900 dark:bg-red-900/10 dark:border-red-900 dark:text-red-400',
            'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-900 dark:bg-yellow-900/10 dark:border-yellow-900 dark:text-yellow-400',
            'success' => 'bg-green-50 border-green-200 text-green-900 dark:bg-green-900/10 dark:border-green-900 dark:text-green-400',
            default => 'bg-gray-50 border-gray-200 text-gray-900',
        };
        
        $iconClass = match($data['alert_type']) {
            'danger' => 'text-red-600 dark:text-red-400',
            'warning' => 'text-yellow-600 dark:text-yellow-400',
            'success' => 'text-green-600 dark:text-green-400',
            default => 'text-gray-600',
        };
    @endphp
    
    <div class="rounded-lg border-2 p-4 {{ $alertClass }}">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0 {{ $iconClass }}">
                @if($data['alert_type'] === 'danger')
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                @elseif($data['alert_type'] === 'warning')
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                @else
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                @endif
            </div>
            
            <div class="flex-1">
                <h3 class="font-semibold text-lg mb-1">
                    @if($data['paper_mode'])
                        📄 Paper Trading Mode
                    @elseif($data['live_enabled'])
                        🔴 LIVE TRADING MODE
                    @else
                        ⚠️ Configuration Issue
                    @endif
                </h3>
                
                <p class="text-sm opacity-90 mb-3">
                    {{ $data['message'] }}
                </p>
                
                <div class="flex flex-wrap gap-4 text-sm">
                    <div>
                        <span class="font-medium">Capital:</span>
                        <span class="font-mono">₹{{ number_format($data['capital']) }}</span>
                    </div>
                    <div>
                        <span class="font-medium">Paper Trades:</span>
                        <span class="font-mono">{{ $data['paper_trades_completed'] }}/30</span>
                    </div>
                    <div>
                        <span class="font-medium">Live Trading:</span>
                        <span class="font-mono">{{ $data['live_enabled'] ? '🔓 Unlocked' : '🔒 Locked' }}</span>
                    </div>
                </div>
                
                <div class="mt-3">
                    <a href="{{ route('filament.admin.resources.settings.index') }}" 
                       class="inline-flex items-center gap-1 text-sm font-medium hover:underline">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Manage Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
