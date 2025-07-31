let calculatedData = null;
const calc = (input, machineIndex) => {

    if (machineIndex > 0) {
        input.cutSpacing = {
            horizontal: 0,
            vertical: 0,
        };
    }

    fetch(`/test/${input.scriptId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        // body: JSON.stringify(input)
    })
        .then(response => response.json())
        .then(data => {
            // console.log(data);
            calculatedData = data;
            // console.log(calculatedData);

            const textualExplanation = document.getElementById("textual-explanation");
            textualExplanation.innerHTML = "";

            for (let i = 0; i < data.length; i++) {
                displayAllTextualExplanation(data[i], data[i].metaData.jobNumber);
            }
        })
        .catch(error => {
            console.error('Error loading JSON:', error);
        });
}


const displayAllTextualExplanation = (data, jobId) => {
    const textualExplanation = document.getElementById("textual-explanation");

    Object.keys(data.parts).map((partId) => {

        const partDiv = document.createElement("div");
        partDiv.className = "border border-[#16161d] rounded-md overflow-hidden mb-4";

        partDiv.innerHTML = "";

        data.parts[partId].actionPaths.map((path) => {
            const uuid = crypto.randomUUID();
            const actionDiv = document.createElement("div");

            let divContent = "";
            divContent += `<div>`;
            divContent += `<div class="p-0">`;
            divContent += `
<div class="text-xs text-uppercase text-white bg-[#16161d] p-1 px-2 font-mono flex flex-row justify-between">
    <div>${jobId} - ${partId}</div>
    <div>
        <a href="/display.html?jobId=${jobId}&partId=${partId}&impId=${path.id}" target="_blank" class="text-yellow-300 hover:text-yellow-300/80 hover:underline">show imposition</a>
    </div>
</div>`;


            divContent += `<div class="title flex flex-row justify-between pb-2 border-b border-gray-400 p-2">
              <div class="flex flex-row gap-2 items-center">
                <div>
                  <input type="radio" name="${jobId}-${partId}" value="${path.id}">
                </div>
                <div class="font-mono text-red-600 bg-gray-100 rounded px-2 py-1 shadow-md text-xs">
                  ${path.pressSheet}
                </div>
                <div class="font-mono text-indigo-600 bg-gray-100 rounded px-2 py-1 shadow-md text-xs">
                  ${Math.round(path.cost*100)/100}€
                </div>
                <div class="font-mono text-green-600 bg-gray-100 rounded px-2 py-1 shadow-md text-xs">
                  ${Math.round(path.duration*100)/100}min
                </div>
              </div>
              <div>
                <a href="#" class="text-xs font-mono text-blue-500 hover:text-blue-700/100 hover:underline show-details text-blue-500 hover:text-blue-700/100 cursor-pointer hover:underline" onclick="toggleDetails('${uuid}')">show breakdown</a>
              </div>
            </div>`;

            divContent += `<div class="hidden details text-xs font-mono p-2 pt-0 bg-gray-100 " id="${uuid}">`;

            divContent += `
              <div class="machine-header flex flex-row gap-4 py-2 border-b border-gray-400 last:border-b-0 font-semibold">
                <div class="w-[150px]">Machine</div>
                <div class="w-[120px]">Zone</div>
                <div class="w-[120px]">Sheets</div>
                <div class="w-[100px]">Imposition</div>
                <div class="w-[100px]">Setup</div>
                <div class="w-[100px]">Run</div>
                <div class="w-[100px]">Cost</div>
              </div>`;


            if (typeof path.aluSheetsCost !== "undefined") {
                divContent += `<div class="machine flex flex-row gap-4 py-2 border-b border-gray-400 last:border-b-0">`;
                divContent += `<div class="w-[150px]">alu. sheets</div>`;
                divContent += `<div class="w-[150px]">-</div>`;
                divContent += `<div class="w-[150px]">-</div>`;
                divContent += `<div class="w-[150px]">-</div>`;
                divContent += `<div class="w-[150px]">-</div>`;
                divContent += `<div class="w-[150px]">-</div>`;
                divContent += `<div class="w-[150px]">${path.aluSheetsCost}€</div>`;
                divContent += `</div>`;
            }

            if (typeof path.paperCost !== "undefined") {
                divContent += `<div class="machine flex flex-row gap-4 py-2 border-b border-gray-400 last:border-b-0">`;
                divContent += `<div class="w-[150px]">paper</div>`;
                divContent += `<div class="w-[150px]">-</div>`;
                divContent += `<div class="w-[150px]">-</div>`;
                divContent += `<div class="w-[150px]">-</div>`;
                divContent += `<div class="w-[150px]">-</div>`;
                divContent += `<div class="w-[150px]">-</div>`;
                divContent += `<div class="w-[150px]">${path.paperCost}€</div>`;
                divContent += `</div>`;
            }

            path.nodes.map((node) => {

                if (node.cost > 0) {
                    divContent += `<div class="machine flex flex-row gap-4 py-2 border-b border-gray-400 last:border-b-0">`;
                    divContent += `<div class="w-[150px]">${node.machine}</div>`;
                    divContent += `<div class="w-[150px]">${node.zone.width}x${node.zone.height}mm</div>`;
                    divContent += `<div class="w-[150px]">${node.todo.cutSheetCount}</div>`;
                    divContent += `<div class="w-[150px]">${node.gridFitting.cols} x ${node.gridFitting.rows} ${node.gridFitting.rotated ? "R" : "U"}</div>`;
                    divContent += `<div class="w-[150px]">${node.setupDuration} min</div>`;
                    divContent += `<div class="w-[150px]">${node.runDuration} min</div>`;
                    divContent += `<div class="w-[150px]">${node.cost}€</div>`;
                    divContent += `</div>`;
                }

                // console.log(node);
            })
            divContent += `</div>`;

            divContent += `</div>`;
            divContent += `</div>`;

            actionDiv.innerHTML = divContent;

            partDiv.appendChild(actionDiv);
        })

        textualExplanation.appendChild(partDiv);

    });

}

const sendToOrdo = () => {

    for (let i in calculatedData) {

        let isAllSelected = true;
        let selectedUuids = Object.keys(calculatedData[i].parts).map((partId) => {
            const selectedValue = document.querySelector(`input[name="${calculatedData[i].metaData.jobNumber}-${partId}"]:checked`)?.value;
            console.log(partId);
            console.log(selectedValue);
            isAllSelected = isAllSelected && !!selectedValue;
            return {partId: partId, value: selectedValue}
        });

        if (isAllSelected) {
            const payload = {
                jobId: calculatedData[i].metaData.jobNumber,
                jobId2: calculatedData[i].metaData.jobId,
                selectedUuids: selectedUuids
            }

            fetch('/ordo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
                .then(response => response.json())
                .then(data => {
                    console.log(data);
                })
                .catch(error => {
                    console.error('Error loading JSON:', error);
                });


        }

    }
}

// 👇 Expose it globally
window.calc = calc;
window.sendToOrdo = sendToOrdo;