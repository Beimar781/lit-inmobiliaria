{{-- Pagos mensuales de un contrato de alquiler. Recibe $cuotas (CronogramaDeAlquiler::cuotas); si está vacío no muestra nada. --}}
@if (! empty($cuotas))
    @php($coloresCuota = ['PAGADA' => 'success', 'PARCIAL' => 'info', 'PENDIENTE' => 'secondary', 'VENCIDA' => 'danger'])
    @php($proximaCuota = \App\Modules\AgendaVisitasPagos\Compartido\CronogramaDeAlquiler::proxima($cuotas))
    @php($usd = fn ($valor) => number_format((float) $valor, 2, '.', ','))

    <div class="card shadow-sm mb-4" id="pagos-mensuales">
        <div class="card-body">
            <h2 class="h5">Pagos mensuales</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-2">
                    <thead class="table-light">
                        <tr>
                            <th>N.º</th>
                            <th>Vence</th>
                            <th class="text-end">Monto</th>
                            <th>Estado</th>
                            <th class="text-end">Falta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cuotas as $cuota)
                            @php($esProxima = $proximaCuota !== null && $proximaCuota['numero'] === $cuota['numero'])
                            <tr @class(['table-warning' => $esProxima])>
                                <td>{{ $cuota['numero'] }}</td>
                                <td>
                                    {{ $cuota['vencimiento']->format('d/m/Y') }}
                                    @if ($esProxima) <span class="badge text-bg-warning ms-1">Próxima</span> @endif
                                </td>
                                <td class="text-end">USD {{ $usd($cuota['monto']) }}</td>
                                <td>
                                    <span class="badge text-bg-{{ $coloresCuota[$cuota['estado']] ?? 'secondary' }}">
                                        {{ \App\Modules\AgendaVisitasPagos\Compartido\CronogramaDeAlquiler::ESTADOS[$cuota['estado']] }}
                                    </span>
                                </td>
                                <td class="text-end">{{ $cuota['estado'] !== 'PAGADA' && ($esProxima || $cuota['pagado'] > 0 || $cuota['estado'] === 'VENCIDA') ? 'USD ' . $usd($cuota['falta']) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mb-0">Los pagos se aplican en orden, de la primera cuota a la última.</p>
        </div>
    </div>
@endif
