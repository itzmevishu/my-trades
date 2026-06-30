<div class="scan-log-chart">
    @if(empty($candles))
        <div class="text-center py-8 text-gray-500">
            <p>No historical candle data available for this scan time.</p>
            <p class="text-sm mt-2">The data may have been cleaned up due to retention policies.</p>
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
