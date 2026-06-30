<div class="scan-log-chart">
    @if(empty($candles))
        <div class="text-center py-12">
            <div class="text-gray-400 mb-4">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">
                📊 No Chart Data Available
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                Historical candle data for this scan time is not in the cache.
            </p>
            
            <div class="max-w-md mx-auto text-left bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                <div class="text-sm text-gray-700 dark:text-gray-300 space-y-2">
                    <p class="font-semibold text-blue-700 dark:text-blue-400">💡 Possible Reasons:</p>
                    <ul class="list-disc list-inside space-y-1 text-xs">
                        <li>Candle cache was recently cleared</li>
                        <li>Data retention policy (30 days) cleaned old data</li>
                        <li>Scan is from before cache implementation</li>
                        <li>System maintenance removed cached data</li>
                    </ul>
                    
                    <p class="font-semibold text-blue-700 dark:text-blue-400 mt-3">📋 Current Scan Info:</p>
                    <div class="text-xs space-y-1">
                        <div><strong>Date:</strong> {{ $scanLog->scan_date->format('M d, Y') }}</div>
                        <div><strong>Time:</strong> {{ \Carbon\Carbon::parse($scanLog->scan_time)->format('H:i:s') }}</div>
                        <div><strong>Price:</strong> {{ number_format($scanLog->current_price, 2) }}</div>
                        @if($scanLog->pattern_detected)
                            <div><strong>Pattern:</strong> {{ ucwords(str_replace('_', ' ', $scanLog->pattern_detected)) }}</div>
                        @endif
                    </div>
                    
                    @php
                        $scanAge = now()->diffInHours($scanLog->scan_date);
                    @endphp
                    
                    @if($scanAge < 72)
                        <div class="mt-3 p-2 bg-green-50 dark:bg-green-900/20 rounded border border-green-200 dark:border-green-800">
                            <p class="text-xs font-semibold text-green-700 dark:text-green-400">✅ Recent Scan</p>
                            <p class="text-xs text-green-600 dark:text-green-500 mt-1">
                                This scan is recent ({{ round($scanAge) }} hours old). Next trading scan will repopulate the cache with fresh data.
                            </p>
                        </div>
                    @else
                        <div class="mt-3 p-2 bg-amber-50 dark:bg-amber-900/20 rounded border border-amber-200 dark:border-amber-800">
                            <p class="text-xs font-semibold text-amber-700 dark:text-amber-400">⚠️ Old Scan</p>
                            <p class="text-xs text-amber-600 dark:text-amber-500 mt-1">
                                This scan is {{ round($scanAge / 24) }} days old. Historical data may have been cleaned per retention policy (30 days).
                            </p>
                        </div>
                    @endif
                    
                    <div class="mt-3 text-center">
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            💡 <strong>Note:</strong> EMA values, pattern details, and rejection reasons are still available in the sections below.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div id="scanLogChart" style="min-height: 500px;"></div>
        
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg">
                <div class="text-xs text-gray-600 dark:text-gray-400">20 EMA</div>
                <div class="text-lg font-semibold text-blue-600 dark:text-blue-400">
                    {{ number_format($scanLog->ema_20, 2) }}
                </div>
            </div>
            <div class="bg-purple-50 dark:bg-purple-900/20 p-3 rounded-lg">
                <div class="text-xs text-gray-600 dark:text-gray-400">100 EMA</div>
                <div class="text-lg font-semibold text-purple-600 dark:text-purple-400">
                    {{ number_format($scanLog->ema_100, 2) }}
                </div>
            </div>
            <div class="bg-orange-50 dark:bg-orange-900/20 p-3 rounded-lg">
                <div class="text-xs text-gray-600 dark:text-gray-400">200 EMA</div>
                <div class="text-lg font-semibold text-orange-600 dark:text-orange-400">
                    {{ number_format($scanLog->ema_200, 2) }}
                </div>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg">
                <div class="text-xs text-gray-600 dark:text-gray-400">Scan Price</div>
                <div class="text-lg font-semibold text-green-600 dark:text-green-400">
                    {{ number_format($scanLog->current_price, 2) }}
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Prepare candle data
                const candleData = @json($candles);
                const scanPrice = {{ $scanLog->current_price }};
                const scanTime = new Date('{{ $scanLog->scan_date->format('Y-m-d') }} {{ \Carbon\Carbon::parse($scanLog->scan_time)->format('H:i:s') }}').getTime();
                const ema20 = {{ $scanLog->ema_20 }};
                const ema100 = {{ $scanLog->ema_100 }};
                const ema200 = {{ $scanLog->ema_200 }};
                
                // Format data for ApexCharts
                const ohlcData = candleData.map(candle => ({
                    x: new Date(candle.timestamp),
                    y: [candle.open, candle.high, candle.low, candle.close]
                }));
                
                // Create horizontal lines for EMAs (approximate - actual EMAs change per candle)
                const emaData = candleData.map(candle => candle.timestamp);
                
                const options = {
                    series: [
                        {
                            name: 'Price',
                            type: 'candlestick',
                            data: ohlcData
                        }
                    ],
                    chart: {
                        type: 'candlestick',
                        height: 500,
                        toolbar: {
                            show: true,
                            tools: {
                                download: true,
                                zoom: true,
                                zoomin: true,
                                zoomout: true,
                                pan: true,
                                reset: true
                            }
                        },
                        animations: {
                            enabled: false
                        }
                    },
                    title: {
                        text: 'BankNifty 15-Min Chart at Scan Time',
                        align: 'left'
                    },
                    annotations: {
                        xaxis: [
                            {
                                x: scanTime,
                                borderColor: '#00E396',
                                strokeDashArray: 0,
                                label: {
                                    borderColor: '#00E396',
                                    style: {
                                        color: '#fff',
                                        background: '#00E396'
                                    },
                                    text: 'Scan Time'
                                }
                            }
                        ],
                        yaxis: [
                            {
                                y: ema20,
                                borderColor: '#2563eb',
                                strokeDashArray: 4,
                                label: {
                                    borderColor: '#2563eb',
                                    style: {
                                        color: '#fff',
                                        background: '#2563eb'
                                    },
                                    text: '20 EMA: ' + ema20.toFixed(2)
                                }
                            },
                            {
                                y: ema100,
                                borderColor: '#9333ea',
                                strokeDashArray: 4,
                                label: {
                                    borderColor: '#9333ea',
                                    style: {
                                        color: '#fff',
                                        background: '#9333ea'
                                    },
                                    text: '100 EMA: ' + ema100.toFixed(2)
                                }
                            },
                            {
                                y: ema200,
                                borderColor: '#ea580c',
                                strokeDashArray: 4,
                                label: {
                                    borderColor: '#ea580c',
                                    style: {
                                        color: '#fff',
                                        background: '#ea580c'
                                    },
                                    text: '200 EMA: ' + ema200.toFixed(2)
                                }
                            },
                            {
                                y: scanPrice,
                                borderColor: '#10b981',
                                strokeDashArray: 0,
                                label: {
                                    borderColor: '#10b981',
                                    style: {
                                        color: '#fff',
                                        background: '#10b981'
                                    },
                                    text: 'Scan: ' + scanPrice.toFixed(2)
                                }
                            }
                        ],
                        @if($scanLog->pattern_detected)
                        points: [
                            {
                                x: scanTime,
                                y: scanPrice,
                                marker: {
                                    size: 8,
                                    fillColor: '{{ $scanLog->pattern_direction === 'bullish' ? '#10b981' : '#ef4444' }}',
                                    strokeColor: '#fff',
                                    strokeWidth: 2,
                                    shape: 'circle'
                                },
                                label: {
                                    borderColor: '{{ $scanLog->pattern_direction === 'bullish' ? '#10b981' : '#ef4444' }}',
                                    offsetY: 0,
                                    style: {
                                        color: '#fff',
                                        background: '{{ $scanLog->pattern_direction === 'bullish' ? '#10b981' : '#ef4444' }}'
                                    },
                                    text: '{{ ucwords(str_replace('_', ' ', $scanLog->pattern_detected)) }}'
                                }
                            }
                        ]
                        @endif
                    },
                    xaxis: {
                        type: 'datetime',
                        labels: {
                            datetimeFormatter: {
                                year: 'yyyy',
                                month: 'MMM \'yy',
                                day: 'dd MMM',
                                hour: 'HH:mm'
                            }
                        }
                    },
                    yaxis: {
                        tooltip: {
                            enabled: true
                        },
                        labels: {
                            formatter: function(value) {
                                return value.toFixed(2);
                            }
                        }
                    },
                    plotOptions: {
                        candlestick: {
                            colors: {
                                upward: '#10b981',
                                downward: '#ef4444'
                            },
                            wick: {
                                useFillColor: true
                            }
                        }
                    },
                    tooltip: {
                        custom: function({seriesIndex, dataPointIndex, w}) {
                            const candle = candleData[dataPointIndex];
                            if (!candle) return '';
                            
                            const change = candle.close - candle.open;
                            const changePercent = ((change / candle.open) * 100).toFixed(2);
                            const changeClass = change >= 0 ? 'text-green-600' : 'text-red-600';
                            
                            return `
                                <div class="p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded shadow-lg" style="min-width: 200px;">
                                    <div class="font-semibold mb-2">${candle.datetime}</div>
                                    <div class="grid grid-cols-2 gap-2 text-sm">
                                        <div class="text-gray-600 dark:text-gray-400">Open:</div>
                                        <div class="font-medium text-right">${candle.open.toFixed(2)}</div>
                                        
                                        <div class="text-gray-600 dark:text-gray-400">High:</div>
                                        <div class="font-medium text-right text-green-600">${candle.high.toFixed(2)}</div>
                                        
                                        <div class="text-gray-600 dark:text-gray-400">Low:</div>
                                        <div class="font-medium text-right text-red-600">${candle.low.toFixed(2)}</div>
                                        
                                        <div class="text-gray-600 dark:text-gray-400">Close:</div>
                                        <div class="font-medium text-right">${candle.close.toFixed(2)}</div>
                                        
                                        <div class="text-gray-600 dark:text-gray-400">Change:</div>
                                        <div class="font-medium text-right ${changeClass}">${change >= 0 ? '+' : ''}${change.toFixed(2)} (${changePercent}%)</div>
                                        
                                        <div class="text-gray-600 dark:text-gray-400">Volume:</div>
                                        <div class="font-medium text-right">${candle.volume.toLocaleString()}</div>
                                    </div>
                                </div>
                            `;
                        }
                    },
                    grid: {
                        borderColor: '#e5e7eb'
                    }
                };
                
                const chart = new ApexCharts(document.querySelector("#scanLogChart"), options);
                chart.render();
            });
        </script>
    @endif
</div>
